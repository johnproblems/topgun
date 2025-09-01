<?php

namespace App\Services;

use App\Contracts\LicensingServiceInterface;
use App\Data\LicenseValidationResult;
use App\Models\EnterpriseLicense;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LicensingService implements LicensingServiceInterface
{
    private const CACHE_TTL = 300; // 5 minutes

    private const LICENSE_KEY_LENGTH = 32;

    private const GRACE_PERIOD_DAYS = 7;

    public function validateLicense(string $licenseKey, ?string $domain = null): LicenseValidationResult
    {
        try {
            // Check cache first for performance
            $cacheKey = "license_validation:{$licenseKey}:".($domain ?? 'no_domain');
            $cachedResult = Cache::get($cacheKey);

            if ($cachedResult) {
                return new LicenseValidationResult(...$cachedResult);
            }

            $license = EnterpriseLicense::where('license_key', $licenseKey)->first();

            if (! $license) {
                $result = new LicenseValidationResult(false, 'License not found');
                $this->cacheValidationResult($cacheKey, $result, 60); // Cache failures for 1 minute

                return $result;
            }

            // Check license status
            if ($license->isRevoked()) {
                $result = new LicenseValidationResult(false, 'License has been revoked', $license);
                $this->cacheValidationResult($cacheKey, $result, 60);

                return $result;
            }

            if ($license->isSuspended()) {
                $result = new LicenseValidationResult(false, 'License is suspended', $license);
                $this->cacheValidationResult($cacheKey, $result, 60);

                return $result;
            }

            // Check expiration with grace period
            if ($license->isExpired()) {
                $daysExpired = abs(now()->diffInDays($license->expires_at, false)); // Get absolute days expired
                if ($daysExpired > self::GRACE_PERIOD_DAYS) {
                    $license->markAsExpired();
                    $result = new LicenseValidationResult(false, 'License expired', $license);
                    $this->cacheValidationResult($cacheKey, $result, 60);

                    return $result;
                } else {
                    // Within grace period - log warning but allow
                    Log::warning("License {$licenseKey} is expired but within grace period", [
                        'license_id' => $license->id,
                        'days_expired' => $daysExpired,
                        'grace_period_days' => self::GRACE_PERIOD_DAYS,
                    ]);
                }
            }

            // Check domain authorization
            if ($domain && ! $this->isDomainAuthorized($license, $domain)) {
                $result = new LicenseValidationResult(
                    false,
                    "Domain '{$domain}' is not authorized for this license",
                    $license,
                    [],
                    ['unauthorized_domain' => $domain]
                );
                $this->cacheValidationResult($cacheKey, $result, 60);

                return $result;
            }

            // Check usage limits
            $usageCheck = $this->checkUsageLimits($license);
            if (! $usageCheck['within_limits']) {
                $result = new LicenseValidationResult(
                    false,
                    'Usage limits exceeded: '.implode(', ', array_column($usageCheck['violations'], 'message')),
                    $license,
                    $usageCheck['violations'],
                    ['usage' => $usageCheck['usage']]
                );
                $this->cacheValidationResult($cacheKey, $result, 30); // Cache limit violations for 30 seconds

                return $result;
            }

            // Update validation timestamp
            $this->refreshValidation($license);

            $result = new LicenseValidationResult(
                true,
                'License is valid',
                $license,
                [],
                [
                    'usage' => $usageCheck['usage'],
                    'expires_at' => $license->expires_at?->toISOString(),
                    'license_tier' => $license->license_tier,
                    'features' => $license->features,
                ]
            );

            $this->cacheValidationResult($cacheKey, $result, self::CACHE_TTL);

            return $result;

        } catch (\Exception $e) {
            Log::error('License validation error', [
                'license_key' => $licenseKey,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new LicenseValidationResult(false, 'License validation failed due to system error');
        }
    }

    public function issueLicense(Organization $organization, array $config): EnterpriseLicense
    {
        $licenseKey = $this->generateLicenseKey($organization, $config);

        $license = EnterpriseLicense::create([
            'organization_id' => $organization->id,
            'license_key' => $licenseKey,
            'license_type' => $config['license_type'] ?? 'subscription',
            'license_tier' => $config['license_tier'] ?? 'basic',
            'features' => $config['features'] ?? [],
            'limits' => $config['limits'] ?? [],
            'issued_at' => now(),
            'expires_at' => $config['expires_at'] ?? null,
            'authorized_domains' => $config['authorized_domains'] ?? [],
            'status' => 'active',
        ]);

        Log::info('License issued', [
            'license_id' => $license->id,
            'organization_id' => $organization->id,
            'license_type' => $license->license_type,
            'license_tier' => $license->license_tier,
        ]);

        // Clear any cached validation results for this organization
        $this->clearLicenseCache($licenseKey);

        return $license;
    }

    public function revokeLicense(EnterpriseLicense $license): bool
    {
        $success = $license->revoke();

        if ($success) {
            Log::warning('License revoked', [
                'license_id' => $license->id,
                'organization_id' => $license->organization_id,
                'license_key' => $license->license_key,
            ]);

            $this->clearLicenseCache($license->license_key);
        }

        return $success;
    }

    public function suspendLicense(EnterpriseLicense $license, ?string $reason = null): bool
    {
        $success = $license->suspend();

        if ($success) {
            Log::warning('License suspended', [
                'license_id' => $license->id,
                'organization_id' => $license->organization_id,
                'license_key' => $license->license_key,
                'reason' => $reason,
            ]);

            $this->clearLicenseCache($license->license_key);
        }

        return $success;
    }

    public function reactivateLicense(EnterpriseLicense $license): bool
    {
        $success = $license->activate();

        if ($success) {
            Log::info('License reactivated', [
                'license_id' => $license->id,
                'organization_id' => $license->organization_id,
                'license_key' => $license->license_key,
            ]);

            $this->clearLicenseCache($license->license_key);
        }

        return $success;
    }

    public function checkUsageLimits(EnterpriseLicense $license): array
    {
        if (! $license->organization) {
            return [
                'within_limits' => false,
                'violations' => [['message' => 'Organization not found']],
                'usage' => [],
            ];
        }

        $usage = $license->organization->getUsageMetrics();
        $limits = $license->limits ?? [];
        $violations = [];

        foreach ($limits as $limitType => $limitValue) {
            $currentUsage = $usage[$limitType] ?? 0;
            if ($currentUsage > $limitValue) {
                $violations[] = [
                    'type' => $limitType,
                    'limit' => $limitValue,
                    'current' => $currentUsage,
                    'message' => ucfirst(str_replace('_', ' ', $limitType))." count ({$currentUsage}) exceeds limit ({$limitValue})",
                ];
            }
        }

        return [
            'within_limits' => empty($violations),
            'violations' => $violations,
            'usage' => $usage,
            'limits' => $limits,
        ];
    }

    public function generateLicenseKey(Organization $organization, array $config): string
    {
        // Create a unique identifier based on organization and timestamp
        $payload = [
            'org_id' => $organization->id,
            'timestamp' => now()->timestamp,
            'tier' => $config['license_tier'] ?? 'basic',
            'type' => $config['license_type'] ?? 'subscription',
            'random' => Str::random(8),
        ];

        // Create a hash of the payload
        $hash = hash('sha256', json_encode($payload).config('app.key'));

        // Take first 32 characters and format as license key
        $key = strtoupper(substr($hash, 0, self::LICENSE_KEY_LENGTH));

        // Format as XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX
        return implode('-', str_split($key, 4));
    }

    public function refreshValidation(EnterpriseLicense $license): bool
    {
        return $license->updateLastValidated();
    }

    public function isDomainAuthorized(EnterpriseLicense $license, string $domain): bool
    {
        return $license->isDomainAuthorized($domain);
    }

    public function getUsageStatistics(EnterpriseLicense $license): array
    {
        $usageCheck = $this->checkUsageLimits($license);
        $usage = $usageCheck['usage'];
        $limits = $usageCheck['limits'];

        $statistics = [];
        foreach ($usage as $type => $current) {
            $limit = $limits[$type] ?? null;
            $statistics[$type] = [
                'current' => $current,
                'limit' => $limit,
                'percentage' => $limit ? round(($current / $limit) * 100, 2) : 0,
                'remaining' => $limit ? max(0, $limit - $current) : null,
                'unlimited' => $limit === null,
            ];
        }

        return [
            'statistics' => $statistics,
            'within_limits' => $usageCheck['within_limits'],
            'violations' => $usageCheck['violations'],
            'last_validated' => $license->last_validated_at?->toISOString(),
            'expires_at' => $license->expires_at?->toISOString(),
            'days_until_expiration' => $license->getDaysUntilExpiration(),
        ];
    }

    private function cacheValidationResult(string $cacheKey, LicenseValidationResult $result, int $ttl): void
    {
        try {
            Cache::put($cacheKey, [
                $result->isValid,
                $result->getMessage(),
                $result->getLicense(),
                $result->getViolations(),
                $result->getMetadata(),
            ], $ttl);
        } catch (\Exception $e) {
            Log::warning('Failed to cache license validation result', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function clearLicenseCache(string $licenseKey): void
    {
        try {
            // Clear all cached validation results for this license key
            $patterns = [
                "license_validation:{$licenseKey}:*",
            ];

            foreach ($patterns as $pattern) {
                Cache::forget($pattern);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear license cache', [
                'license_key' => $licenseKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
