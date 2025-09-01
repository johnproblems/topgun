<?php

namespace App\Traits;

use App\Contracts\LicensingServiceInterface;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

trait LicenseValidation
{
    /**
     * Check if the current user's organization has a valid license for the given feature
     */
    protected function validateLicenseForFeature(string $feature, ?string $action = null): ?JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $license = $organization->activeLicense;
        if (! $license) {
            return response()->json([
                'error' => 'Valid license required for this feature',
                'feature' => $feature,
                'license_required' => true,
            ], 403);
        }

        if (! $license->hasFeature($feature)) {
            return response()->json([
                'error' => 'Feature not available in your license tier',
                'feature' => $feature,
                'current_tier' => $license->license_tier,
                'upgrade_required' => true,
            ], 403);
        }

        return null; // License is valid
    }

    /**
     * Check if the current organization is within usage limits for resource creation
     */
    protected function validateUsageLimits(?string $resourceType = null): ?JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $license = $organization->activeLicense;
        if (! $license) {
            return response()->json(['error' => 'Valid license required'], 403);
        }

        $licensingService = app(LicensingServiceInterface::class);
        $usageCheck = $licensingService->checkUsageLimits($license);

        if (! $usageCheck['within_limits']) {
            // Check if specific resource type is over limit
            if ($resourceType) {
                $resourceViolations = collect($usageCheck['violations'])
                    ->where('type', $resourceType)
                    ->first();

                if ($resourceViolations) {
                    return response()->json([
                        'error' => "Cannot create {$resourceType}: limit exceeded",
                        'limit' => $resourceViolations['limit'],
                        'current' => $resourceViolations['current'],
                        'resource_type' => $resourceType,
                    ], 403);
                }
            }

            return response()->json([
                'error' => 'Usage limits exceeded',
                'violations' => $usageCheck['violations'],
                'current_usage' => $usageCheck['usage'],
                'limits' => $usageCheck['limits'],
            ], 403);
        }

        return null; // Within limits
    }

    /**
     * Check if server creation is allowed based on license
     */
    protected function validateServerCreation(): ?JsonResponse
    {
        // Check server management feature
        $featureCheck = $this->validateLicenseForFeature('server_management');
        if ($featureCheck) {
            return $featureCheck;
        }

        // Check server count limits
        return $this->validateUsageLimits('servers');
    }

    /**
     * Check if application deployment is allowed based on license
     */
    protected function validateApplicationDeployment(): ?JsonResponse
    {
        // Check application deployment feature
        $featureCheck = $this->validateLicenseForFeature('application_deployment');
        if ($featureCheck) {
            return $featureCheck;
        }

        // Check application count limits
        return $this->validateUsageLimits('applications');
    }

    /**
     * Check if domain management is allowed based on license
     */
    protected function validateDomainManagement(): ?JsonResponse
    {
        // Check domain management feature
        $featureCheck = $this->validateLicenseForFeature('domain_management');
        if ($featureCheck) {
            return $featureCheck;
        }

        // Check domain count limits
        return $this->validateUsageLimits('domains');
    }

    /**
     * Check if infrastructure provisioning is allowed based on license
     */
    protected function validateInfrastructureProvisioning(): ?JsonResponse
    {
        // Check cloud provisioning feature
        $featureCheck = $this->validateLicenseForFeature('cloud_provisioning');
        if ($featureCheck) {
            return $featureCheck;
        }

        // Check cloud provider limits
        return $this->validateUsageLimits('cloud_providers');
    }

    /**
     * Get license-based feature flags for the current organization
     */
    protected function getLicenseFeatures(): array
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return [];
        }

        $license = $organization->activeLicense;
        if (! $license) {
            return [];
        }

        return [
            'license_tier' => $license->license_tier,
            'features' => $license->features ?? [],
            'limits' => $license->limits ?? [],
            'expires_at' => $license->expires_at?->toISOString(),
            'is_trial' => $license->isTrial(),
            'days_until_expiration' => $license->getDaysUntilExpiration(),
        ];
    }

    /**
     * Get current organization from user context
     */
    protected function getCurrentOrganization(): ?Organization
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return $user->currentOrganization ?? $user->organizations()->first();
    }

    /**
     * Check if a specific deployment option is available based on license
     */
    protected function validateDeploymentOption(string $option): ?JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $license = $organization->activeLicense;
        if (! $license) {
            return response()->json(['error' => 'Valid license required'], 403);
        }

        // Define deployment options by license tier
        $tierOptions = [
            'basic' => [
                'docker_deployment',
                'basic_monitoring',
            ],
            'professional' => [
                'docker_deployment',
                'basic_monitoring',
                'advanced_monitoring',
                'blue_green_deployment',
                'auto_scaling',
                'backup_management',
            ],
            'enterprise' => [
                'docker_deployment',
                'basic_monitoring',
                'advanced_monitoring',
                'blue_green_deployment',
                'auto_scaling',
                'backup_management',
                'multi_region_deployment',
                'advanced_security',
                'compliance_reporting',
                'custom_integrations',
            ],
        ];

        $availableOptions = $tierOptions[$license->license_tier] ?? [];

        if (! in_array($option, $availableOptions)) {
            return response()->json([
                'error' => "Deployment option '{$option}' not available in {$license->license_tier} tier",
                'available_options' => $availableOptions,
                'upgrade_required' => true,
            ], 403);
        }

        return null; // Option is available
    }

    /**
     * Add license information to API responses
     */
    protected function addLicenseInfoToResponse(array $data): array
    {
        $licenseFeatures = $this->getLicenseFeatures();

        return array_merge($data, [
            'license_info' => $licenseFeatures,
        ]);
    }

    /**
     * Check if the current license allows a specific resource limit
     */
    protected function checkResourceLimit(string $resourceType, int $requestedCount = 1): ?JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $license = $organization->activeLicense;
        if (! $license) {
            return response()->json(['error' => 'Valid license required'], 403);
        }

        $usage = $organization->getUsageMetrics();
        $limits = $license->limits ?? [];

        $currentUsage = $usage[$resourceType] ?? 0;
        $limit = $limits[$resourceType] ?? null;

        if ($limit !== null && ($currentUsage + $requestedCount) > $limit) {
            return response()->json([
                'error' => "Cannot create {$requestedCount} {$resourceType}: would exceed limit",
                'current_usage' => $currentUsage,
                'requested' => $requestedCount,
                'limit' => $limit,
                'available' => max(0, $limit - $currentUsage),
            ], 403);
        }

        return null; // Within limits
    }
}
