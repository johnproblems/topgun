<?php

namespace App\Http\Middleware;

use App\Contracts\LicensingServiceInterface;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LicenseValidationMiddleware
{
    protected LicensingServiceInterface $licensingService;

    public function __construct(LicensingServiceInterface $licensingService)
    {
        $this->licensingService = $licensingService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ?string $feature = null, ?string $action = null)
    {
        // Skip license validation for localhost/development
        if (app()->environment('local') && config('app.debug')) {
            return $next($request);
        }

        // Get current user's organization
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $organization = $user->currentOrganization ?? $user->organizations()->first();
        if (! $organization) {
            return response()->json(['error' => 'No organization found'], 403);
        }

        // Get active license for the organization
        $license = $organization->activeLicense;
        if (! $license) {
            return $this->handleNoLicense($request, $action);
        }

        // Validate license
        $domain = $request->getHost();
        $validationResult = $this->licensingService->validateLicense($license->license_key, $domain);

        if (! $validationResult->isValid) {
            Log::warning('License validation failed', [
                'organization_id' => $organization->id,
                'license_key' => $license->license_key,
                'domain' => $domain,
                'reason' => $validationResult->getMessage(),
                'action' => $action,
                'feature' => $feature,
            ]);

            return $this->handleInvalidLicense($request, $validationResult, $action);
        }

        // Check feature-specific permissions
        if ($feature && ! $license->hasFeature($feature)) {
            return response()->json([
                'error' => 'Feature not available in your license tier',
                'feature' => $feature,
                'license_tier' => $license->license_tier,
                'upgrade_required' => true,
            ], 403);
        }

        // Check usage limits for resource creation actions
        if ($this->isResourceCreationAction($action)) {
            $usageCheck = $this->licensingService->checkUsageLimits($license);
            if (! $usageCheck['within_limits']) {
                return response()->json([
                    'error' => 'Usage limits exceeded',
                    'violations' => $usageCheck['violations'],
                    'current_usage' => $usageCheck['usage'],
                    'limits' => $usageCheck['limits'],
                ], 403);
            }
        }

        // Store license info in request for controllers to use
        $request->attributes->set('license', $license);
        $request->attributes->set('organization', $organization);
        $request->attributes->set('license_validation', $validationResult);

        return $next($request);
    }

    protected function handleNoLicense(Request $request, ?string $action): \Illuminate\Http\JsonResponse
    {
        // Allow basic read operations without license
        if ($this->isReadOnlyAction($action)) {
            return response()->json([
                'warning' => 'No active license found. Some features may be limited.',
                'license_required' => true,
            ]);
        }

        return response()->json([
            'error' => 'Valid license required for this operation',
            'action' => $action,
            'license_required' => true,
        ], 403);
    }

    protected function handleInvalidLicense(Request $request, $validationResult, ?string $action): \Illuminate\Http\JsonResponse
    {
        $license = $validationResult->getLicense();

        // Check if license is expired but within grace period
        if ($license && $license->isExpired() && $license->isWithinGracePeriod()) {
            $daysRemaining = $license->getDaysRemainingInGracePeriod();

            // Allow operations during grace period but show warning
            if ($this->isGracePeriodAllowedAction($action)) {
                $request->attributes->set('grace_period_warning', true);
                $request->attributes->set('grace_period_days', $daysRemaining);

                return response()->json([
                    'warning' => "License expired but within grace period. {$daysRemaining} days remaining.",
                    'grace_period' => true,
                    'days_remaining' => $daysRemaining,
                ]);
            }
        }

        return response()->json([
            'error' => $validationResult->getMessage(),
            'license_status' => $license?->status,
            'expires_at' => $license?->expires_at?->toISOString(),
            'violations' => $validationResult->getViolations(),
        ], 403);
    }

    protected function isResourceCreationAction(?string $action): bool
    {
        $creationActions = [
            'create_server',
            'create_application',
            'deploy_application',
            'create_domain',
            'provision_infrastructure',
            'create_database',
            'create_service',
        ];

        return in_array($action, $creationActions);
    }

    protected function isReadOnlyAction(?string $action): bool
    {
        $readOnlyActions = [
            'view_servers',
            'view_applications',
            'view_domains',
            'view_dashboard',
            'view_metrics',
            'list_resources',
        ];

        return in_array($action, $readOnlyActions);
    }

    protected function isGracePeriodAllowedAction(?string $action): bool
    {
        $allowedActions = [
            'view_servers',
            'view_applications',
            'view_domains',
            'view_dashboard',
            'manage_license',
            'renew_license',
        ];

        return in_array($action, $allowedActions);
    }
}
