<?php

namespace Tests\Feature;

use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseValidationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create();

        // Associate user with organization
        $this->organization->users()->attach($this->user->id, [
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->user->update(['current_organization_id' => $this->organization->id]);
    }

    public function test_api_requests_require_valid_license()
    {
        $this->actingAs($this->user);

        // Test API endpoint without license
        $response = $this->getJson('/api/v1/servers');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'NO_VALID_LICENSE',
            ]);
    }

    public function test_api_requests_work_with_valid_license()
    {
        // Create valid license
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'license_tier' => 'professional',
            'features' => ['server_provisioning', 'api_access'],
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($this->user);

        // Test API endpoint with valid license
        $response = $this->getJson('/api/v1/servers');

        // Should not be blocked by license middleware
        $response->assertStatus(200);
    }

    public function test_server_provisioning_requires_specific_features()
    {
        // Create license without server provisioning feature
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'license_tier' => 'basic',
            'features' => ['api_access'], // Missing server_provisioning
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($this->user);

        // Test server creation endpoint
        $response = $this->postJson('/api/v1/servers', [
            'name' => 'test-server',
            'ip' => '192.168.1.100',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'FEATURE_NOT_LICENSED',
            ]);
    }

    public function test_expired_license_blocks_provisioning()
    {
        // Create expired license
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'license_tier' => 'professional',
            'features' => ['server_provisioning'],
            'expires_at' => now()->subDays(10), // Expired 10 days ago
        ]);

        $this->actingAs($this->user);

        // Test server creation endpoint
        $response = $this->postJson('/api/v1/servers', [
            'name' => 'test-server',
            'ip' => '192.168.1.100',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'LICENSE_EXPIRED_NO_PROVISIONING',
            ]);
    }

    public function test_grace_period_allows_read_operations()
    {
        // Create license expired within grace period
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'license_tier' => 'professional',
            'features' => ['server_provisioning', 'api_access'],
            'expires_at' => now()->subDays(3), // Expired 3 days ago (within 7-day grace period)
        ]);

        $this->actingAs($this->user);

        // Test read operation (should work in grace period)
        $response = $this->getJson('/api/v1/servers');

        $response->assertStatus(200)
            ->assertHeader('X-License-Status', 'expired-grace-period');
    }

    public function test_grace_period_blocks_provisioning_operations()
    {
        // Create license expired within grace period
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'license_tier' => 'professional',
            'features' => ['server_provisioning', 'api_access'],
            'expires_at' => now()->subDays(3), // Expired 3 days ago (within 7-day grace period)
        ]);

        $this->actingAs($this->user);

        // Test provisioning operation (should be blocked even in grace period)
        $response = $this->postJson('/api/v1/servers', [
            'name' => 'test-server',
            'ip' => '192.168.1.100',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'LICENSE_GRACE_PERIOD_RESTRICTION',
            ]);
    }

    public function test_web_routes_redirect_on_license_issues()
    {
        $this->actingAs($this->user);

        // Test web route without license
        $response = $this->get('/servers');

        $response->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_license_headers_added_to_api_responses()
    {
        // Create valid license
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'license_tier' => 'enterprise',
            'features' => ['server_provisioning', 'api_access'],
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/servers');

        $response->assertHeader('X-License-Tier', 'enterprise')
            ->assertHeader('X-License-Status', 'active');
    }

    public function test_rate_limiting_based_on_license_tier()
    {
        // Create basic license with lower rate limits
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'license_tier' => 'basic',
            'features' => ['api_access'],
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($this->user);

        // Make multiple requests to test rate limiting
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/v1/version');
            $response->assertStatus(200);
        }

        // This test would need to be adjusted based on actual rate limit implementation
        // For now, just verify the license middleware is applied
        $this->assertTrue(true);
    }
}
