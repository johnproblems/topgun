<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Services\LicensingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LicenseController extends Controller
{
    protected LicensingService $licensingService;

    public function __construct(LicensingService $licensingService)
    {
        $this->licensingService = $licensingService;
    }

    /**
     * Get license data for the current user/organization
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization) {
                return response()->json([
                    'licenses' => [],
                    'currentLicense' => null,
                    'usageStats' => null,
                    'canIssueLicenses' => false,
                    'canManageAllLicenses' => false,
                ]);
            }

            // Get current license
            $currentLicense = $currentOrganization->activeLicense;

            // Get usage statistics if license exists
            $usageStats = null;
            if ($currentLicense) {
                $usageStats = $this->licensingService->getUsageStatistics($currentLicense);
            }

            // Check permissions
            $canIssueLicenses = $currentOrganization->canUserPerformAction($user, 'issue_licenses');
            $canManageAllLicenses = $currentOrganization->canUserPerformAction($user, 'manage_all_licenses');

            // Get all licenses if user can manage them
            $licenses = [];
            if ($canManageAllLicenses) {
                $licenses = EnterpriseLicense::with('organization')
                    ->orderBy('created_at', 'desc')
                    ->get();
            } elseif ($currentLicense) {
                $licenses = [$currentLicense];
            }

            return response()->json([
                'licenses' => $licenses,
                'currentLicense' => $currentLicense,
                'usageStats' => $usageStats,
                'canIssueLicenses' => $canIssueLicenses,
                'canManageAllLicenses' => $canManageAllLicenses,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load license data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Issue a new license
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'organization_id' => 'required|exists:organizations,id',
                'license_type' => 'required|in:trial,subscription,perpetual',
                'license_tier' => 'required|in:basic,professional,enterprise',
                'expires_at' => 'nullable|date|after:now',
                'features' => 'array',
                'features.*' => 'string',
                'limits' => 'array',
                'limits.max_users' => 'nullable|integer|min:1',
                'limits.max_servers' => 'nullable|integer|min:1',
                'limits.max_applications' => 'nullable|integer|min:1',
                'limits.max_domains' => 'nullable|integer|min:1',
                'authorized_domains' => 'array',
                'authorized_domains.*' => 'string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization || ! $currentOrganization->canUserPerformAction($user, 'issue_licenses')) {
                return response()->json([
                    'message' => 'Insufficient permissions to issue licenses',
                ], 403);
            }

            $organization = Organization::findOrFail($request->organization_id);

            $config = [
                'license_type' => $request->license_type,
                'license_tier' => $request->license_tier,
                'expires_at' => $request->expires_at ? new \DateTime($request->expires_at) : null,
                'features' => $request->features ?? [],
                'limits' => array_filter($request->limits ?? []),
                'authorized_domains' => array_filter($request->authorized_domains ?? []),
            ];

            $license = $this->licensingService->issueLicense($organization, $config);

            return response()->json([
                'message' => 'License issued successfully',
                'license' => $license->load('organization'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to issue license',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get license details and usage statistics
     */
    public function show(string $id): JsonResponse
    {
        try {
            $license = EnterpriseLicense::with('organization')->findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization ||
                (! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses') &&
                 $license->organization_id !== $currentOrganization->id)) {
                return response()->json([
                    'message' => 'Insufficient permissions to view this license',
                ], 403);
            }

            $usageStats = $this->licensingService->getUsageStatistics($license);

            return response()->json([
                'license' => $license,
                'usageStats' => $usageStats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load license details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a license
     */
    public function validateLicense(Request $request, string $id): JsonResponse
    {
        try {
            $license = EnterpriseLicense::findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization ||
                (! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses') &&
                 $license->organization_id !== $currentOrganization->id)) {
                return response()->json([
                    'message' => 'Insufficient permissions to validate this license',
                ], 403);
            }

            $domain = $request->input('domain', $request->getHost());
            $result = $this->licensingService->validateLicense($license->license_key, $domain);

            return response()->json([
                'valid' => $result->isValid(),
                'message' => $result->getMessage(),
                'violations' => $result->getViolations(),
                'metadata' => $result->getMetadata(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to validate license',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suspend a license
     */
    public function suspend(Request $request, string $id): JsonResponse
    {
        try {
            $license = EnterpriseLicense::findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization || ! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses')) {
                return response()->json([
                    'message' => 'Insufficient permissions to suspend licenses',
                ], 403);
            }

            $reason = $request->input('reason', 'Suspended by administrator');
            $success = $this->licensingService->suspendLicense($license, $reason);

            if ($success) {
                return response()->json([
                    'message' => 'License suspended successfully',
                ]);
            } else {
                return response()->json([
                    'message' => 'Failed to suspend license',
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to suspend license',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reactivate a license
     */
    public function reactivate(string $id): JsonResponse
    {
        try {
            $license = EnterpriseLicense::findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization || ! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses')) {
                return response()->json([
                    'message' => 'Insufficient permissions to reactivate licenses',
                ], 403);
            }

            $success = $this->licensingService->reactivateLicense($license);

            if ($success) {
                return response()->json([
                    'message' => 'License reactivated successfully',
                ]);
            } else {
                return response()->json([
                    'message' => 'Failed to reactivate license',
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reactivate license',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke a license
     */
    public function revoke(string $id): JsonResponse
    {
        try {
            $license = EnterpriseLicense::findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization || ! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses')) {
                return response()->json([
                    'message' => 'Insufficient permissions to revoke licenses',
                ], 403);
            }

            $success = $this->licensingService->revokeLicense($license);

            if ($success) {
                return response()->json([
                    'message' => 'License revoked successfully',
                ]);
            } else {
                return response()->json([
                    'message' => 'Failed to revoke license',
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to revoke license',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Renew a license
     */
    public function renew(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'renewal_period' => 'required|in:1_month,3_months,1_year,custom',
                'custom_expires_at' => 'required_if:renewal_period,custom|date|after:now',
                'auto_renewal' => 'boolean',
                'payment_method' => 'required|in:credit_card,bank_transfer,invoice',
                'new_expires_at' => 'required|date|after:now',
                'cost' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $license = EnterpriseLicense::findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization ||
                (! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses') &&
                 $license->organization_id !== $currentOrganization->id)) {
                return response()->json([
                    'message' => 'Insufficient permissions to renew this license',
                ], 403);
            }

            // Update license expiration
            $license->expires_at = new \DateTime($request->new_expires_at);
            $license->save();

            // Here you would typically process payment based on payment_method
            // For now, we'll just update the license

            return response()->json([
                'message' => 'License renewed successfully',
                'license' => $license->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to renew license',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upgrade a license
     */
    public function upgrade(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_tier' => 'required|in:basic,professional,enterprise',
                'upgrade_type' => 'required|in:immediate,next_billing',
                'payment_method' => 'required_if:upgrade_type,immediate|in:credit_card,bank_transfer',
                'prorated_cost' => 'required_if:upgrade_type,immediate|numeric|min:0',
                'new_monthly_cost' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $license = EnterpriseLicense::findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization ||
                (! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses') &&
                 $license->organization_id !== $currentOrganization->id)) {
                return response()->json([
                    'message' => 'Insufficient permissions to upgrade this license',
                ], 403);
            }

            // Validate upgrade path
            $tierHierarchy = ['basic', 'professional', 'enterprise'];
            $currentIndex = array_search($license->license_tier, $tierHierarchy);
            $newIndex = array_search($request->new_tier, $tierHierarchy);

            if ($newIndex <= $currentIndex) {
                return response()->json([
                    'message' => 'Cannot downgrade or upgrade to the same tier',
                ], 422);
            }

            // Update license tier and features based on new tier
            $license->license_tier = $request->new_tier;

            // Set features based on tier
            $tierFeatures = [
                'basic' => ['application_deployment', 'database_management', 'ssl_certificates'],
                'professional' => [
                    'application_deployment', 'database_management', 'ssl_certificates',
                    'server_provisioning', 'terraform_integration', 'white_label_branding',
                    'organization_hierarchy', 'mfa_authentication', 'audit_logging',
                ],
                'enterprise' => [
                    'application_deployment', 'database_management', 'ssl_certificates',
                    'server_provisioning', 'terraform_integration', 'white_label_branding',
                    'organization_hierarchy', 'mfa_authentication', 'audit_logging',
                    'multi_cloud_support', 'payment_processing', 'domain_management',
                    'advanced_rbac', 'compliance_reporting',
                ],
            ];

            $license->features = $tierFeatures[$request->new_tier];

            // Update limits based on tier
            $tierLimits = [
                'basic' => ['max_users' => 5, 'max_servers' => 3, 'max_applications' => 10],
                'professional' => ['max_users' => 25, 'max_servers' => 15, 'max_applications' => 50],
                'enterprise' => [], // Unlimited
            ];

            $license->limits = $tierLimits[$request->new_tier];
            $license->save();

            // Here you would typically process payment if upgrade_type is 'immediate'
            // For now, we'll just update the license

            return response()->json([
                'message' => 'License upgraded successfully',
                'license' => $license->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upgrade license',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get usage history for a license
     */
    public function usageHistory(string $id): JsonResponse
    {
        try {
            $license = EnterpriseLicense::findOrFail($id);

            // Check permissions
            $user = Auth::user();
            $currentOrganization = $user->currentOrganization;

            if (! $currentOrganization ||
                (! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses') &&
                 $license->organization_id !== $currentOrganization->id)) {
                return response()->json([
                    'message' => 'Insufficient permissions to view usage history',
                ], 403);
            }

            // Mock usage history - in real implementation, this would come from a usage tracking system
            $history = [];
            for ($i = 30; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $usage = $license->organization->getUsageMetrics();

                // Add some variation to make it realistic
                $variation = rand(-2, 2);
                $history[] = [
                    'date' => $date->toDateString(),
                    'users' => max(1, $usage['users'] + $variation),
                    'servers' => max(0, $usage['servers'] + $variation),
                    'applications' => max(0, $usage['applications'] + $variation),
                    'domains' => max(0, $usage['domains'] + $variation),
                    'within_limits' => $license->isWithinLimits(),
                ];
            }

            return response()->json([
                'history' => $history,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load usage history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export usage data
     */
    public function exportUsage(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $license = EnterpriseLicense::findOrFail($id);

        // Check permissions
        $user = Auth::user();
        $currentOrganization = $user->currentOrganization;

        if (! $currentOrganization ||
            (! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses') &&
             $license->organization_id !== $currentOrganization->id)) {
            abort(403, 'Insufficient permissions to export usage data');
        }

        $filename = "usage-data-{$license->license_key}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($license) {
            $handle = fopen('php://output', 'w');

            // CSV headers
            fputcsv($handle, ['Date', 'Users', 'Servers', 'Applications', 'Domains', 'Within Limits']);

            // Mock data - in real implementation, get from usage tracking system
            for ($i = 30; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $usage = $license->organization->getUsageMetrics();

                fputcsv($handle, [
                    $date->toDateString(),
                    $usage['users'],
                    $usage['servers'],
                    $usage['applications'],
                    $usage['domains'],
                    $license->isWithinLimits() ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export license data
     */
    public function exportLicense(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $license = EnterpriseLicense::with('organization')->findOrFail($id);

        // Check permissions
        $user = Auth::user();
        $currentOrganization = $user->currentOrganization;

        if (! $currentOrganization ||
            (! $currentOrganization->canUserPerformAction($user, 'manage_all_licenses') &&
             $license->organization_id !== $currentOrganization->id)) {
            abort(403, 'Insufficient permissions to export license data');
        }

        $filename = "license-{$license->license_key}-".now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($license) {
            $data = [
                'license' => $license->toArray(),
                'usage_stats' => $this->licensingService->getUsageStatistics($license),
                'exported_at' => now()->toISOString(),
            ];

            echo json_encode($data, JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
