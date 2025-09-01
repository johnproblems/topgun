<?php

namespace App\Contracts;

use App\Models\EnterpriseLicense;
use App\Models\Organization;

interface LicensingServiceInterface
{
    /**
     * Validate a license key with optional domain checking
     */
    public function validateLicense(string $licenseKey, ?string $domain = null): \App\Data\LicenseValidationResult;

    /**
     * Issue a new license for an organization
     */
    public function issueLicense(Organization $organization, array $config): EnterpriseLicense;

    /**
     * Revoke an existing license
     */
    public function revokeLicense(EnterpriseLicense $license): bool;

    /**
     * Check if an organization is within usage limits
     */
    public function checkUsageLimits(EnterpriseLicense $license): array;

    /**
     * Generate a secure license key
     */
    public function generateLicenseKey(Organization $organization, array $config): string;

    /**
     * Refresh license validation timestamp
     */
    public function refreshValidation(EnterpriseLicense $license): bool;

    /**
     * Check if a domain is authorized for a license
     */
    public function isDomainAuthorized(EnterpriseLicense $license, string $domain): bool;

    /**
     * Get license usage statistics
     */
    public function getUsageStatistics(EnterpriseLicense $license): array;

    /**
     * Suspend a license
     */
    public function suspendLicense(EnterpriseLicense $license, ?string $reason = null): bool;

    /**
     * Reactivate a suspended license
     */
    public function reactivateLicense(EnterpriseLicense $license): bool;
}
