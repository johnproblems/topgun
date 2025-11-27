<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Data\LicenseValidationResult validateLicense(string $licenseKey, string $domain = null)
 * @method static \App\Models\EnterpriseLicense issueLicense(\App\Models\Organization $organization, array $config)
 * @method static bool revokeLicense(\App\Models\EnterpriseLicense $license)
 * @method static array checkUsageLimits(\App\Models\EnterpriseLicense $license)
 * @method static string generateLicenseKey(\App\Models\Organization $organization, array $config)
 * @method static bool refreshValidation(\App\Models\EnterpriseLicense $license)
 * @method static bool isDomainAuthorized(\App\Models\EnterpriseLicense $license, string $domain)
 * @method static array getUsageStatistics(\App\Models\EnterpriseLicense $license)
 * @method static bool suspendLicense(\App\Models\EnterpriseLicense $license, string $reason = null)
 * @method static bool reactivateLicense(\App\Models\EnterpriseLicense $license)
 */
class Licensing extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'licensing';
    }
}
