<?php

namespace App\Http\Middleware;

use App\Contracts\LicensingServiceInterface;
use App\Models\EnterpriseLicense;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateLicense
{
    public function __construct(
        protected LicensingServiceInterface $licensingService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        // Skip license validation in development mode
        if (isDev()) {
            return $next($request);
        }

        // Skip license validation for health checks and basic endpoints
        if ($this->shouldSkipValidation($request)) {
            return $next($request);
        }

        $user = Auth::user();
        $organization = $user?->currentOrganization;

        // If no organization context, check for system-wide license
        if (! $organization) {
            return $this->handleNoOrganization($request, $next, $features);
        }

        // Get the active license for the organization
        $license = $organization->activeLicense;
        if (! $license) {
            return $this->handleNoLicense($request, $features);
        }

        // Validate the license
        $domain = $request->getHost();
        $validationResult = $this->licensingService->validateLicense($license->license_key, $domain);

        if (! $validationResult->isValid()) {
            return $this->handleInvalidLicense($request, $validationResult, $features);
        }

        // Check feature-specific permissions
        if (! empty($features) && ! $this->hasRequiredFeatures($license, $features)) {
            return $this->handleMissingFeatures($request, $license, $features);
        }

        // Add license information to request for downstream use
        $request->attributes->set('license', $license);
        $request->attributes->set('license_validation', $validationResult);

        return $next($request);
    }

    /**
     * Determine if license validation should be skipped for this request
     */
    protected function shouldSkipValidation(Request $request): bool
    {
        $skipPaths = [
            '/health',
            '/api/v1/health',
            '/api/feedback',
            '/login',
            '/register',
            '/password/reset',
            '/email/verify',
        ];

        $path = $request->path();

        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, trim($skipPath, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle requests when no organization context is available
     */
    protected function handleNoOrganization(Request $request, Closure $next, array $features): Response
    {
        // For API requests, return JSON error
        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context available. Please ensure you are associated with an organization.',
                'error_code' => 'NO_ORGANIZATION_CONTEXT',
            ], 403);
        }

        // For web requests, redirect to organization setup
        return redirect()->route('organization.setup')
            ->with('error', 'Please set up or join an organization to continue.');
    }

    /**
     * Handle requests when no valid license is found
     */
    protected function handleNoLicense(Request $request, array $features): Response
    {
        Log::warning('License validation failed: No active license found', [
            'user_id' => Auth::id(),
            'organization_id' => Auth::user()?->currentOrganization?->id,
            'path' => $request->path(),
            'required_features' => $features,
        ]);

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json([
                'success' => false,
                'message' => 'No valid license found. Please contact your administrator to obtain a license.',
                'error_code' => 'NO_VALID_LICENSE',
                'required_features' => $features,
            ], 403);
        }

        return redirect()->route('license.required')
            ->with('error', 'A valid license is required to access this feature.')
            ->with('required_features', $features);
    }

    /**
     * Handle requests when license validation fails
     */
    protected function handleInvalidLicense(Request $request, $validationResult, array $features): Response
    {
        $license = $validationResult->getLicense();
        $isExpired = $license && $license->isExpired();
        $isWithinGracePeriod = $isExpired && $license->isWithinGracePeriod();

        Log::warning('License validation failed', [
            'user_id' => Auth::id(),
            'organization_id' => Auth::user()?->currentOrganization?->id,
            'license_id' => $license?->id,
            'path' => $request->path(),
            'validation_message' => $validationResult->getMessage(),
            'violations' => $validationResult->getViolations(),
            'is_expired' => $isExpired,
            'is_within_grace_period' => $isWithinGracePeriod,
            'required_features' => $features,
        ]);

        // Handle expired licenses with graceful degradation
        if ($isExpired && $isWithinGracePeriod) {
            return $this->handleGracePeriodAccess($request, $next, $license, $features);
        }

        $errorData = [
            'success' => false,
            'message' => $validationResult->getMessage(),
            'error_code' => $this->getErrorCode($validationResult),
            'violations' => $validationResult->getViolations(),
            'required_features' => $features,
        ];

        if ($license) {
            $errorData['license_status'] = $license->status;
            $errorData['license_tier'] = $license->license_tier;
            if ($isExpired) {
                $errorData['expired_at'] = $license->expires_at?->toISOString();
                $errorData['days_expired'] = abs($license->getDaysUntilExpiration());
            }
        }

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json($errorData, 403);
        }

        return redirect()->route('license.invalid')
            ->with('error', $validationResult->getMessage())
            ->with('license_data', $errorData);
    }

    /**
     * Handle access during license grace period with limited functionality
     */
    protected function handleGracePeriodAccess(Request $request, Closure $next, EnterpriseLicense $license, array $features): Response
    {
        // During grace period, allow read-only operations but restrict critical features
        $restrictedFeatures = [
            'server_provisioning',
            'infrastructure_provisioning',
            'payment_processing',
            'domain_management',
            'terraform_integration',
        ];

        $hasRestrictedFeature = ! empty(array_intersect($features, $restrictedFeatures));

        if ($hasRestrictedFeature) {
            $errorMessage = 'License expired. Some features are restricted during the grace period.';

            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'error_code' => 'LICENSE_GRACE_PERIOD_RESTRICTION',
                    'restricted_features' => array_intersect($features, $restrictedFeatures),
                    'days_expired' => abs($license->getDaysUntilExpiration()),
                ], 403);
            }

            return redirect()->back()
                ->with('warning', $errorMessage)
                ->with('license_expired', true);
        }

        // Allow the request but add warning headers/context
        $response = $next($request);

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            $response->headers->set('X-License-Status', 'expired-grace-period');
            $response->headers->set('X-License-Days-Expired', abs($license->getDaysUntilExpiration()));
        }

        return $response;
    }

    /**
     * Handle requests when required features are missing from license
     */
    protected function handleMissingFeatures(Request $request, EnterpriseLicense $license, array $features): Response
    {
        $missingFeatures = array_diff($features, $license->features ?? []);

        Log::warning('License feature validation failed', [
            'user_id' => Auth::id(),
            'organization_id' => $license->organization_id,
            'license_id' => $license->id,
            'license_tier' => $license->license_tier,
            'path' => $request->path(),
            'required_features' => $features,
            'missing_features' => $missingFeatures,
            'available_features' => $license->features,
        ]);

        $errorData = [
            'success' => false,
            'message' => 'Your license does not include the required features for this operation.',
            'error_code' => 'INSUFFICIENT_LICENSE_FEATURES',
            'required_features' => $features,
            'missing_features' => $missingFeatures,
            'license_tier' => $license->license_tier,
            'available_features' => $license->features ?? [],
        ];

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json($errorData, 403);
        }

        return redirect()->route('license.upgrade')
            ->with('error', 'Your current license does not include the required features.')
            ->with('license_data', $errorData);
    }

    /**
     * Check if license has all required features
     */
    protected function hasRequiredFeatures(EnterpriseLicense $license, array $requiredFeatures): bool
    {
        if (empty($requiredFeatures)) {
            return true;
        }

        $licenseFeatures = $license->features ?? [];

        return empty(array_diff($requiredFeatures, $licenseFeatures));
    }

    /**
     * Get appropriate error code based on validation result
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
}
