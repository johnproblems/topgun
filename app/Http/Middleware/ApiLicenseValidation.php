<?php

namespace App\Http\Middleware;

use App\Contracts\LicensingServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiLicenseValidation
{
    public function __construct(
        protected LicensingServiceInterface $licensingService
    ) {}

    /**
     * Handle an incoming request for API endpoints with license validation
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        // Skip license validation in development mode
        if (isDev()) {
            return $next($request);
        }

        // Skip validation for health checks and public endpoints
        if ($this->shouldSkipValidation($request)) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user) {
            return $this->unauthorizedResponse('Authentication required');
        }

        $organization = $user->currentOrganization;
        if (! $organization) {
            return $this->forbiddenResponse(
                'No organization context available',
                'NO_ORGANIZATION_CONTEXT'
            );
        }

        $license = $organization->activeLicense;
        if (! $license) {
            return $this->forbiddenResponse(
                'No valid license found for organization',
                'NO_VALID_LICENSE'
            );
        }

        // Validate license
        $domain = $request->getHost();
        $validationResult = $this->licensingService->validateLicense($license->license_key, $domain);

        if (! $validationResult->isValid()) {
            return $this->handleInvalidLicense($request, $validationResult, $features);
        }

        // Check feature-specific permissions
        if (! empty($features)) {
            $featureCheck = $this->validateFeatures($license, $features);
            if (! $featureCheck['valid']) {
                return $this->forbiddenResponse(
                    $featureCheck['message'],
                    'INSUFFICIENT_LICENSE_FEATURES',
                    [
                        'required_features' => $features,
                        'missing_features' => $featureCheck['missing_features'],
                        'license_tier' => $license->license_tier,
                        'available_features' => $license->features ?? [],
                    ]
                );
            }
        }

        // Apply rate limiting based on license tier
        $this->applyRateLimiting($request, $license);

        // Add license context to request
        $request->attributes->set('license', $license);
        $request->attributes->set('license_validation', $validationResult);
        $request->attributes->set('organization', $organization);

        // Add license information to response headers
        $response = $next($request);
        $this->addLicenseHeaders($response, $license, $validationResult);

        return $response;
    }

    /**
     * Determine if license validation should be skipped
     */
    protected function shouldSkipValidation(Request $request): bool
    {
        $skipPaths = [
            'api/health',
            'api/v1/health',
            'api/feedback',
        ];

        $path = trim($request->path(), '/');

        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle invalid license validation results
     */
    protected function handleInvalidLicense(Request $request, $validationResult, array $features): Response
    {
        $license = $validationResult->getLicense();
        $isExpired = $license && $license->isExpired();
        $isWithinGracePeriod = $isExpired && $license->isWithinGracePeriod();

        Log::warning('API license validation failed', [
            'user_id' => Auth::id(),
            'organization_id' => Auth::user()?->currentOrganization?->id,
            'license_id' => $license?->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'validation_message' => $validationResult->getMessage(),
            'violations' => $validationResult->getViolations(),
            'is_expired' => $isExpired,
            'is_within_grace_period' => $isWithinGracePeriod,
            'required_features' => $features,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        // Handle grace period with restricted access
        if ($isExpired && $isWithinGracePeriod) {
            return $this->handleGracePeriodAccess($request, $license, $features);
        }

        $errorCode = $this->getErrorCode($validationResult);
        $errorData = [
            'validation_message' => $validationResult->getMessage(),
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

        return $this->forbiddenResponse(
            $validationResult->getMessage(),
            $errorCode,
            $errorData
        );
    }

    /**
     * Handle API access during license grace period
     */
    protected function handleGracePeriodAccess(Request $request, $license, array $features): Response
    {
        // Define features that are restricted during grace period
        $restrictedFeatures = [
            'server_provisioning',
            'infrastructure_provisioning',
            'terraform_integration',
            'payment_processing',
            'domain_management',
            'bulk_operations',
        ];

        $hasRestrictedFeature = ! empty(array_intersect($features, $restrictedFeatures));

        if ($hasRestrictedFeature) {
            return $this->forbiddenResponse(
                'License expired. This feature is restricted during the grace period.',
                'LICENSE_GRACE_PERIOD_RESTRICTION',
                [
                    'restricted_features' => array_intersect($features, $restrictedFeatures),
                    'days_expired' => abs($license->getDaysUntilExpiration()),
                    'grace_period_ends' => $license->getGracePeriodEndDate()?->toISOString(),
                ]
            );
        }

        // Allow read-only operations with warnings
        return response()->json([
            'success' => true,
            'message' => 'Request processed with license in grace period',
            'warnings' => [
                'license_expired' => true,
                'days_expired' => abs($license->getDaysUntilExpiration()),
                'grace_period_ends' => $license->getGracePeriodEndDate()?->toISOString(),
                'restricted_features' => $restrictedFeatures,
            ],
        ], 200);
    }

    /**
     * Validate required features against license
     */
    protected function validateFeatures($license, array $requiredFeatures): array
    {
        if (empty($requiredFeatures)) {
            return ['valid' => true];
        }

        $licenseFeatures = $license->features ?? [];
        $missingFeatures = array_diff($requiredFeatures, $licenseFeatures);

        if (! empty($missingFeatures)) {
            return [
                'valid' => false,
                'message' => 'License does not include required features: '.implode(', ', $missingFeatures),
                'missing_features' => $missingFeatures,
            ];
        }

        return ['valid' => true];
    }

    /**
     * Apply rate limiting based on license tier
     */
    protected function applyRateLimiting(Request $request, $license): void
    {
        $tier = $license->license_tier ?? 'basic';
        $rateLimits = $this->getRateLimitsForTier($tier);

        $key = 'api_rate_limit:'.$license->organization_id.':'.$request->ip();

        $executed = RateLimiter::attempt(
            $key,
            $rateLimits['max_attempts'],
            function () {
                // Rate limit passed
            },
            $rateLimits['decay_minutes'] * 60
        );

        if (! $executed) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new \Illuminate\Http\Exceptions\ThrottleRequestsException(
                'API rate limit exceeded for license tier: '.$tier,
                null,
                [],
                $retryAfter
            );
        }
    }

    /**
     * Get rate limits configuration for license tier
     */
    protected function getRateLimitsForTier(string $tier): array
    {
        return match ($tier) {
            'enterprise' => [
                'max_attempts' => 10000,
                'decay_minutes' => 60,
            ],
            'professional' => [
                'max_attempts' => 5000,
                'decay_minutes' => 60,
            ],
            'basic' => [
                'max_attempts' => 1000,
                'decay_minutes' => 60,
            ],
            default => [
                'max_attempts' => 100,
                'decay_minutes' => 60,
            ],
        };
    }

    /**
     * Add license information to response headers
     */
    protected function addLicenseHeaders(Response $response, $license, $validationResult): void
    {
        $response->headers->set('X-License-Tier', $license->license_tier);
        $response->headers->set('X-License-Status', $license->status);

        if ($license->expires_at) {
            $response->headers->set('X-License-Expires', $license->expires_at->toISOString());
            $response->headers->set('X-License-Days-Remaining', $license->getDaysUntilExpiration());
        }

        $usageStats = $this->licensingService->getUsageStatistics($license);
        if (isset($usageStats['statistics'])) {
            foreach ($usageStats['statistics'] as $type => $stats) {
                if (isset($stats['percentage'])) {
                    $response->headers->set("X-Usage-{$type}", $stats['percentage'].'%');
                }
            }
        }
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
     * Return standardized unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
        ], 401);
    }

    /**
     * Return standardized forbidden response
     */
    protected function forbiddenResponse(string $message, string $errorCode, array $data = []): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            ...$data,
        ], 403);
    }
}
