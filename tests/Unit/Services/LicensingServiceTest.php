<?php

namespace Tests\Unit\Services;

use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Services\LicensingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LicensingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LicensingService $licensingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->licensingService = new LicensingService;
    }

    public function test_validates_active_license()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'expires_at' => now()->addYear(),
            'features' => ['basic_features'],
            'limits' => ['users' => 10, 'servers' => 5],
        ]);

        $result = $this->licensingService->validateLicense($license->license_key);

        $this->assertTrue($result->isValid());
        $this->assertEquals('License is valid', $result->getMessage());
        $this->assertEquals($license->id, $result->getLicense()->id);
    }

    public function test_rejects_expired_license()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'expires_at' => now()->subDays(10), // Expired beyond grace period
        ]);

        $result = $this->licensingService->validateLicense($license->license_key);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('expired', $result->getMessage());
    }

    public function test_allows_license_within_grace_period()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'expires_at' => now()->subDays(3), // Within 7-day grace period
            'limits' => [],
        ]);

        $result = $this->licensingService->validateLicense($license->license_key);

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_revoked_license()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'revoked',
        ]);

        $result = $this->licensingService->validateLicense($license->license_key);

        $this->assertFalse($result->isValid());
        $this->assertEquals('License has been revoked', $result->getMessage());
    }

    public function test_rejects_suspended_license()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'suspended',
        ]);

        $result = $this->licensingService->validateLicense($license->license_key);

        $this->assertFalse($result->isValid());
        $this->assertEquals('License is suspended', $result->getMessage());
    }

    public function test_validates_authorized_domain()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'authorized_domains' => ['example.com', '*.subdomain.com'],
            'limits' => [],
        ]);

        // Test exact domain match
        $result = $this->licensingService->validateLicense($license->license_key, 'example.com');
        $this->assertTrue($result->isValid());

        // Test wildcard domain match
        $result = $this->licensingService->validateLicense($license->license_key, 'test.subdomain.com');
        $this->assertTrue($result->isValid());

        // Test unauthorized domain
        $result = $this->licensingService->validateLicense($license->license_key, 'unauthorized.com');
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not authorized', $result->getMessage());
    }

    public function test_checks_usage_limits()
    {
        $organization = Organization::factory()->create();

        // Create some users and servers to exceed limits
        $organization->users()->attach(
            \App\Models\User::factory()->count(3)->create(),
            ['role' => 'member', 'is_active' => true]
        );

        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'limits' => ['users' => 2], // Limit exceeded
        ]);

        $result = $this->licensingService->validateLicense($license->license_key);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Usage limits exceeded', $result->getMessage());
        $this->assertTrue($result->hasViolations());
    }

    public function test_generates_unique_license_keys()
    {
        $organization = Organization::factory()->create();

        $config = [
            'license_type' => 'subscription',
            'license_tier' => 'professional',
        ];

        $key1 = $this->licensingService->generateLicenseKey($organization, $config);
        $key2 = $this->licensingService->generateLicenseKey($organization, $config);

        $this->assertNotEquals($key1, $key2);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key1);
    }

    public function test_issues_license_successfully()
    {
        $organization = Organization::factory()->create();

        $config = [
            'license_type' => 'subscription',
            'license_tier' => 'professional',
            'features' => ['advanced_features', 'api_access'],
            'limits' => ['users' => 50, 'servers' => 20],
            'expires_at' => now()->addYear(),
            'authorized_domains' => ['example.com'],
        ];

        $license = $this->licensingService->issueLicense($organization, $config);

        $this->assertEquals($organization->id, $license->organization_id);
        $this->assertEquals('subscription', $license->license_type);
        $this->assertEquals('professional', $license->license_tier);
        $this->assertEquals('active', $license->status);
        $this->assertEquals(['advanced_features', 'api_access'], $license->features);
        $this->assertEquals(['users' => 50, 'servers' => 20], $license->limits);
        $this->assertNotNull($license->license_key);
    }

    public function test_revokes_license_successfully()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);

        $result = $this->licensingService->revokeLicense($license);

        $this->assertTrue($result);
        $this->assertEquals('revoked', $license->fresh()->status);
    }

    public function test_suspends_and_reactivates_license()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);

        // Test suspension
        $result = $this->licensingService->suspendLicense($license, 'Payment failure');
        $this->assertTrue($result);
        $this->assertEquals('suspended', $license->fresh()->status);

        // Test reactivation
        $result = $this->licensingService->reactivateLicense($license);
        $this->assertTrue($result);
        $this->assertEquals('active', $license->fresh()->status);
    }

    public function test_caches_validation_results()
    {
        Cache::flush();

        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'limits' => [],
        ]);

        // First call should hit database
        $result1 = $this->licensingService->validateLicense($license->license_key);
        $this->assertTrue($result1->isValid());

        // Second call should use cache
        $result2 = $this->licensingService->validateLicense($license->license_key);
        $this->assertTrue($result2->isValid());
    }

    public function test_returns_usage_statistics()
    {
        $organization = Organization::factory()->create();
        $organization->users()->attach(
            \App\Models\User::factory()->count(3)->create(),
            ['role' => 'member', 'is_active' => true]
        );

        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'limits' => ['users' => 10, 'servers' => 5],
        ]);

        $stats = $this->licensingService->getUsageStatistics($license);

        $this->assertArrayHasKey('statistics', $stats);
        $this->assertArrayHasKey('within_limits', $stats);
        $this->assertArrayHasKey('users', $stats['statistics']);
        $this->assertEquals(3, $stats['statistics']['users']['current']);
        $this->assertEquals(10, $stats['statistics']['users']['limit']);
        $this->assertEquals(30, $stats['statistics']['users']['percentage']);
        $this->assertEquals(7, $stats['statistics']['users']['remaining']);
    }

    public function test_handles_nonexistent_license()
    {
        $result = $this->licensingService->validateLicense('INVALID-LICENSE-KEY');

        $this->assertFalse($result->isValid());
        $this->assertEquals('License not found', $result->getMessage());
    }
}
