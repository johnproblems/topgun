<?php

namespace App\Services;

use App\Contracts\LicensingServiceInterface;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

class ResourceProvisioningService
{
    protected LicensingServiceInterface $licensingService;

    public function __construct(LicensingServiceInterface $licensingService)
    {
        $this->licensingService = $licensingService;
    }

    /**
     * Check if organization can provision a new server
     */
    public function canProvisionServer(Organization $organization): array
    {
        $license = $organization->activeLicense;
        if (! $license) {
            return [
                'allowed' => false,
                'reason' => 'No active license found',
                'code' => 'NO_LICENSE',
            ];
        }

        // Check server management feature
        if (! $license->hasFeature('server_management')) {
            return [
                'allowed' => false,
                'reason' => 'Server management not available in your license tier',
                'code' => 'FEATURE_NOT_AVAILABLE',
                'upgrade_required' => true,
            ];
        }

        // Check server count limits
        $usageCheck = $this->licensingService->checkUsageLimits($license);
        if (! $usageCheck['within_limits']) {
            $serverViolations = collect($usageCheck['violations'])
                ->where('type', 'servers')
                ->first();

            if ($serverViolations) {
                return [
                    'allowed' => false,
                    'reason' => 'Server limit exceeded',
                    'code' => 'LIMIT_EXCEEDED',
                    'current_usage' => $serverViolations['current'],
                    'limit' => $serverViolations['limit'],
                ];
            }
        }

        return [
            'allowed' => true,
            'remaining_servers' => $license->getRemainingLimit('servers'),
        ];
    }

    /**
     * Check if organization can deploy a new application
     */
    public function canDeployApplication(Organization $organization): array
    {
        $license = $organization->activeLicense;
        if (! $license) {
            return [
                'allowed' => false,
                'reason' => 'No active license found',
                'code' => 'NO_LICENSE',
            ];
        }

        // Check application deployment feature
        if (! $license->hasFeature('application_deployment')) {
            return [
                'allowed' => false,
                'reason' => 'Application deployment not available in your license tier',
                'code' => 'FEATURE_NOT_AVAILABLE',
                'upgrade_required' => true,
            ];
        }

        // Check application count limits
        $usageCheck = $this->licensingService->checkUsageLimits($license);
        if (! $usageCheck['within_limits']) {
            $appViolations = collect($usageCheck['violations'])
                ->where('type', 'applications')
                ->first();

            if ($appViolations) {
                return [
                    'allowed' => false,
                    'reason' => 'Application limit exceeded',
                    'code' => 'LIMIT_EXCEEDED',
                    'current_usage' => $appViolations['current'],
                    'limit' => $appViolations['limit'],
                ];
            }
        }

        return [
            'allowed' => true,
            'remaining_applications' => $license->getRemainingLimit('applications'),
        ];
    }

    /**
     * Check if organization can manage domains
     */
    public function canManageDomains(Organization $organization): array
    {
        $license = $organization->activeLicense;
        if (! $license) {
            return [
                'allowed' => false,
                'reason' => 'No active license found',
                'code' => 'NO_LICENSE',
            ];
        }

        // Check domain management feature
        if (! $license->hasFeature('domain_management')) {
            return [
                'allowed' => false,
                'reason' => 'Domain management not available in your license tier',
                'code' => 'FEATURE_NOT_AVAILABLE',
                'upgrade_required' => true,
            ];
        }

        // Check domain count limits
        $usageCheck = $this->licensingService->checkUsageLimits($license);
        if (! $usageCheck['within_limits']) {
            $domainViolations = collect($usageCheck['violations'])
                ->where('type', 'domains')
                ->first();

            if ($domainViolations) {
                return [
                    'allowed' => false,
                    'reason' => 'Domain limit exceeded',
                    'code' => 'LIMIT_EXCEEDED',
                    'current_usage' => $domainViolations['current'],
                    'limit' => $domainViolations['limit'],
                ];
            }
        }

        return [
            'allowed' => true,
            'remaining_domains' => $license->getRemainingLimit('domains'),
        ];
    }

    /**
     * Check if organization can provision cloud infrastructure
     */
    public function canProvisionInfrastructure(Organization $organization): array
    {
        $license = $organization->activeLicense;
        if (! $license) {
            return [
                'allowed' => false,
                'reason' => 'No active license found',
                'code' => 'NO_LICENSE',
            ];
        }

        // Check cloud provisioning feature
        if (! $license->hasFeature('cloud_provisioning')) {
            return [
                'allowed' => false,
                'reason' => 'Cloud provisioning not available in your license tier',
                'code' => 'FEATURE_NOT_AVAILABLE',
                'upgrade_required' => true,
            ];
        }

        // Check cloud provider limits
        $usageCheck = $this->licensingService->checkUsageLimits($license);
        if (! $usageCheck['within_limits']) {
            $cloudViolations = collect($usageCheck['violations'])
                ->where('type', 'cloud_providers')
                ->first();

            if ($cloudViolations) {
                return [
                    'allowed' => false,
                    'reason' => 'Cloud provider limit exceeded',
                    'code' => 'LIMIT_EXCEEDED',
                    'current_usage' => $cloudViolations['current'],
                    'limit' => $cloudViolations['limit'],
                ];
            }
        }

        return [
            'allowed' => true,
            'remaining_cloud_providers' => $license->getRemainingLimit('cloud_providers'),
        ];
    }

    /**
     * Get available deployment options based on license tier
     */
    public function getAvailableDeploymentOptions(Organization $organization): array
    {
        $license = $organization->activeLicense;
        if (! $license) {
            return [
                'available_options' => [],
                'license_tier' => null,
            ];
        }

        $tierOptions = [
            'basic' => [
                'docker_deployment' => 'Standard Docker deployment',
                'basic_monitoring' => 'Basic resource monitoring',
                'manual_scaling' => 'Manual application scaling',
            ],
            'professional' => [
                'docker_deployment' => 'Standard Docker deployment',
                'basic_monitoring' => 'Basic resource monitoring',
                'manual_scaling' => 'Manual application scaling',
                'advanced_monitoring' => 'Advanced metrics and alerting',
                'blue_green_deployment' => 'Blue-green deployment strategy',
                'auto_scaling' => 'Automatic application scaling',
                'backup_management' => 'Automated backup management',
                'force_rebuild' => 'Force rebuild deployments',
                'instant_deployment' => 'Instant deployment without queuing',
            ],
            'enterprise' => [
                'docker_deployment' => 'Standard Docker deployment',
                'basic_monitoring' => 'Basic resource monitoring',
                'manual_scaling' => 'Manual application scaling',
                'advanced_monitoring' => 'Advanced metrics and alerting',
                'blue_green_deployment' => 'Blue-green deployment strategy',
                'auto_scaling' => 'Automatic application scaling',
                'backup_management' => 'Automated backup management',
                'force_rebuild' => 'Force rebuild deployments',
                'instant_deployment' => 'Instant deployment without queuing',
                'multi_region_deployment' => 'Multi-region deployment coordination',
                'advanced_security' => 'Advanced security scanning and policies',
                'compliance_reporting' => 'Compliance and audit reporting',
                'custom_integrations' => 'Custom webhook and API integrations',
                'canary_deployment' => 'Canary deployment strategy',
                'rollback_automation' => 'Automated rollback on failure',
            ],
        ];

        $availableOptions = $tierOptions[$license->license_tier] ?? [];

        return [
            'available_options' => $availableOptions,
            'license_tier' => $license->license_tier,
            'expires_at' => $license->expires_at?->toISOString(),
        ];
    }

    /**
     * Check if a specific deployment option is available
     */
    public function isDeploymentOptionAvailable(Organization $organization, string $option): bool
    {
        $availableOptions = $this->getAvailableDeploymentOptions($organization);

        return array_key_exists($option, $availableOptions['available_options']);
    }

    /**
     * Get resource limits for the organization
     */
    public function getResourceLimits(Organization $organization): array
    {
        $license = $organization->activeLicense;
        if (! $license) {
            return [
                'has_license' => false,
                'limits' => [],
                'usage' => [],
            ];
        }

        $usage = $organization->getUsageMetrics();
        $limits = $license->limits ?? [];

        $resourceLimits = [];
        foreach (['servers', 'applications', 'domains', 'cloud_providers'] as $resource) {
            $limit = $limits[$resource] ?? null;
            $current = $usage[$resource] ?? 0;

            $resourceLimits[$resource] = [
                'current' => $current,
                'limit' => $limit,
                'unlimited' => $limit === null,
                'remaining' => $limit ? max(0, $limit - $current) : null,
                'percentage_used' => $limit ? round(($current / $limit) * 100, 2) : 0,
                'near_limit' => $limit ? ($current / $limit) >= 0.8 : false,
            ];
        }

        return [
            'has_license' => true,
            'license_tier' => $license->license_tier,
            'limits' => $resourceLimits,
            'usage' => $usage,
            'expires_at' => $license->expires_at?->toISOString(),
        ];
    }

    /**
     * Log resource provisioning attempt
     */
    public function logProvisioningAttempt(Organization $organization, string $resourceType, bool $allowed, ?string $reason = null): void
    {
        Log::info('Resource provisioning attempt', [
            'organization_id' => $organization->id,
            'resource_type' => $resourceType,
            'allowed' => $allowed,
            'reason' => $reason,
            'license_tier' => $organization->activeLicense?->license_tier,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
