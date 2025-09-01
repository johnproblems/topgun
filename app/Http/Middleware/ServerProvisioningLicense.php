<?php

namespace App\Http\Middleware;

use App\Contracts\LicensingServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ServerProvisioningLicense
{
    public function __construct(
        protected LicensingServiceInterface $licensingService
    ) {}

    /**
     * Handle an incoming request for server provisioning operations
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip license validation in development mode
        if (isDev()) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user) {
            return $this->unauthorizedResponse('Authentication required for server provisioning');
        }

        $organization = $user->currentOrganization;
        if (! $organization) {
            return $this->forbiddenResponse(
                'Organization context required for server provisioning',
                'NO_ORGANIZATION_CONTEXT'
            );
        }

        $license = $organization->activeLicense;
        if (! $license) {
            return $this->forbiddenResponse(
                'Valid license required for server provisioning',
                'NO_VALID_LICENSE'
            );
        }

        // Validate license
        $domain = $request->getHost();
        $validationResult = $this->licensingService->validateLicense($license->license_key, $domain);

        if (! $validationResult->isValid()) {
            return $this->handleInvalidLicense($request, $validationResult);
        }

        // Check server provisioning specific requirements
        $provisioningCheck = $this->validateProvisioningCapabilities($license, $organization);
        if (! $provisioningCheck['allowed']) {
            return $this->forbiddenResponse(
                $provisioningCheck['message'],
                $provisioningCheck['error_code'],
                $provisioningCheck['data']
            );
        }

        // Log provisioning attempt for audit
        Log::info('Server provisioning authorized', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'license_id' => $license->id,
            'license_tier' => $license->license_tier,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'current_server_count' => $organization->servers()->count(),
            'server_limit' => $license->limits['max_servers'] ?? 'unlimited',
        ]);

        // Add provisioning context to request
        $request->attributes->set('license', $license);
        $request->attributes->set('organization', $organization);
        $request->attributes->set('provisioning_authorized', true);

        return $next($request);
    }

    /**
     * Validate server provisioning capabilities against license
     */
    protected function validateProvisioningCapabilities($license, $organization): array
    {
        // Check if license includes server provisioning feature
        $requiredFeatures = ['server_provisioning'];
        $licenseFeatures = $license->features ?? [];
        $missingFeatures = array_diff($requiredFeatures, $licenseFeatures);

        if (! empty($missingFeatures)) {
            return [
                'allowed' => false,
                'message' => 'License does not include server provisioning capabilities',
                'error_code' => 'FEATURE_NOT_LICENSED',
                'data' => [
                    'required_features' => $requiredFeatures,
                    'missing_features' => $missingFeatures,
                    'license_tier' => $license->license_tier,
                    'available_features' => $licenseFeatures,
                ],
            ];
        }

        // Check server count limits
        $currentServerCount = $organization->servers()->count();
        $maxServers = $license->limits['max_servers'] ?? null;

        if ($maxServers !== null && $currentServerCount >= $maxServers) {
            return [
                'allowed' => false,
                'message' => "Server limit reached. Current: {$currentServerCount}, Limit: {$maxServers}",
                'error_code' => 'SERVER_LIMIT_EXCEEDED',
                'data' => [
                    'current_servers' => $currentServerCount,
                    'max_servers' => $maxServers,
                    'license_tier' => $license->license_tier,
                ],
            ];
        }

        // Check if license is expired (no grace period for provisioning)
        if ($license->isExpired()) {
            return [
                'allowed' => false,
                'message' => 'Cannot provision servers with expired license',
                'error_code' => 'LICENSE_EXPIRED_NO_PROVISIONING',
                'data' => [
                    'expired_at' => $license->expires_at?->toISOString(),
                    'days_expired' => abs($license->getDaysUntilExpiration()),
                ],
            ];
        }

        // Check infrastructure provisioning feature for Terraform-based provisioning
        $isInfrastructureProvisioning = $this->isInfrastructureProvisioningRequest($request ?? request());
        if ($isInfrastructureProvisioning) {
            $infraFeatures = ['infrastructure_provisioning', 'terraform_integration'];
            $missingInfraFeatures = array_diff($infraFeatures, $licenseFeatures);

            if (! empty($missingInfraFeatures)) {
                return [
                    'allowed' => false,
                    'message' => 'License does not include infrastructure provisioning capabilities',
                    'error_code' => 'INFRASTRUCTURE_FEATURE_NOT_LICENSED',
                    'data' => [
                        'required_features' => $infraFeatures,
                        'missing_features' => $missingInfraFeatures,
                        'license_tier' => $license->license_tier,
                    ],
                ];
            }
        }

        // Check cloud provider limits if applicable
        $cloudProviderCheck = $this->validateCloudProviderLimits($license, $organization);
        if (! $cloudProviderCheck['allowed']) {
            return $cloudProviderCheck;
        }

        return ['allowed' => true];
    }

    /**
     * Check if this is an infrastructure provisioning request (Terraform-based)
     */
    protected function isInfrastructureProvisioningRequest(Request $request): bool
    {
        $path = $request->path();
        $infrastructurePaths = [
            'api/v1/infrastructure',
            'api/v1/terraform',
            'api/v1/cloud-providers',
            'infrastructure/provision',
            'terraform/deploy',
        ];

        foreach ($infrastructurePaths as $infraPath) {
            if (str_contains($path, $infraPath)) {
                return true;
            }
        }

        // Check request data for infrastructure provisioning indicators
        $data = $request->all();

        return isset($data['provider_credential_id']) ||
               isset($data['terraform_config']) ||
               isset($data['cloud_provider']);
    }

    /**
     * Validate cloud provider specific limits
     */
    protected function validateCloudProviderLimits($license, $organization): array
    {
        $limits = $license->limits ?? [];

        // Check cloud provider count limits
        if (isset($limits['max_cloud_providers'])) {
            $currentProviders = $organization->cloudProviderCredentials()->count();
            if ($currentProviders >= $limits['max_cloud_providers']) {
                return [
                    'allowed' => false,
                    'message' => "Cloud provider limit reached. Current: {$currentProviders}, Limit: {$limits['max_cloud_providers']}",
                    'error_code' => 'CLOUD_PROVIDER_LIMIT_EXCEEDED',
                    'data' => [
                        'current_providers' => $currentProviders,
                        'max_providers' => $limits['max_cloud_providers'],
                    ],
                ];
            }
        }

        // Check concurrent provisioning limits
        if (isset($limits['max_concurrent_provisioning'])) {
            $activeProvisioningCount = $organization->terraformDeployments()
                ->whereIn('status', ['pending', 'provisioning', 'in_progress'])
                ->count();

            if ($activeProvisioningCount >= $limits['max_concurrent_provisioning']) {
                return [
                    'allowed' => false,
                    'message' => "Concurrent provisioning limit reached. Active: {$activeProvisioningCount}, Limit: {$limits['max_concurrent_provisioning']}",
                    'error_code' => 'CONCURRENT_PROVISIONING_LIMIT_EXCEEDED',
                    'data' => [
                        'active_provisioning' => $activeProvisioningCount,
                        'max_concurrent' => $limits['max_concurrent_provisioning'],
                    ],
                ];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Handle invalid license for provisioning
     */
    protected function handleInvalidLicense(Request $request, $validationResult): Response
    {
        $license = $validationResult->getLicense();

        Log::warning('Server provisioning blocked due to invalid license', [
            'user_id' => Auth::id(),
            'organization_id' => Auth::user()?->currentOrganization?->id,
            'license_id' => $license?->id,
            'endpoint' => $request->path(),
            'validation_message' => $validationResult->getMessage(),
            'violations' => $validationResult->getViolations(),
        ]);

        $errorCode = $this->getErrorCode($validationResult);
        $errorData = [
            'validation_message' => $validationResult->getMessage(),
            'violations' => $validationResult->getViolations(),
            'provisioning_blocked' => true,
        ];

        if ($license) {
            $errorData['license_status'] = $license->status;
            $errorData['license_tier'] = $license->license_tier;
            if ($license->isExpired()) {
                $errorData['expired_at'] = $license->expires_at?->toISOString();
                $errorData['days_expired'] = abs($license->getDaysUntilExpiration());
            }
        }

        return $this->forbiddenResponse(
            'Server provisioning not allowed: '.$validationResult->getMessage(),
            $errorCode,
            $errorData
        );
    }

    /**
     * Get error code from validation result
     */
    protected function getErrorCode($validationResult): string
    {
        $message = strtolower($validationResult->getMessage());

        if (str_contains($message, 'expired')) {
            return 'LICENSE_EXPIRED';
        }

        if (str_contains($message, 'revoked')) {
            return 'LICENSE_REVOKED';
        }

        if (str_contains($message, 'suspended')) {
            return 'LICENSE_SUSPENDED';
        }

        if (str_contains($message, 'domain')) {
            return 'DOMAIN_NOT_AUTHORIZED';
        }

        if (str_contains($message, 'usage') || str_contains($message, 'limit')) {
            return 'USAGE_LIMITS_EXCEEDED';
        }

        return 'LICENSE_VALIDATION_FAILED';
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => 'UNAUTHORIZED',
            ], 401);
        }

        return redirect()->route('login')
            ->with('error', $message);
    }

    /**
     * Return forbidden response
     */
    protected function forbiddenResponse(string $message, string $errorCode, array $data = []): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => $errorCode,
                ...$data,
            ], 403);
        }

        return redirect()->back()
            ->with('error', $message)
            ->with('error_data', $data);
    }
}
