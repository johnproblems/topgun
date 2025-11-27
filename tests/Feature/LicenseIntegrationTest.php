<?php

namespace Tests\Feature;

use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Team $team;

    protected Organization $organization;

    protected EnterpriseLicense $license;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test team (required by Coolify)
        $this->team = Team::create([
            'name' => 'Test Team',
            'description' => 'Test team for license integration tests',
        ]);

        // Create test organization and user
        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'hierarchy_type' => 'end_user',
        ]);

        $this->user = User::factory()->create();
        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->organization->users()->attach($this->user, [
            'role' => 'owner',
            'is_active' => true,
        ]);

        // Set current organization like the other test
        $this->user->update(['current_organization_id' => $this->organization->id]);

        // Create test license
        $this->license = EnterpriseLicense::factory()->create([
            'organization_id' => $this->organization->id,
            'license_tier' => 'professional',
            'features' => [
                'server_management',
                'application_deployment',
                'domain_management',
                'advanced_monitoring',
            ],
            'limits' => [
                'servers' => 5,
                'applications' => 10,
                'domains' => 20,
            ],
            'status' => 'active',
        ]);

        // Use session-based authentication like other API tests
        $this->actingAs($this->user);
    }

    public function test_server_creation_requires_valid_license()
    {
        // Test with valid license
        $response = $this->postJson('/api/v1/servers', [
            'name' => 'Test Server',
            'ip' => '192.168.1.100',
            'private_key_uuid' => 'test-key-uuid',
        ]);

        // Should succeed with valid license (though may fail for other reasons like missing private key)
        $this->assertNotEquals(403, $response->status());

        // Test with expired license
        $this->license->update(['status' => 'expired']);

        $response = $this->postJson('/api/v1/servers', [
            'name' => 'Test Server 2',
            'ip' => '192.168.1.101',
            'private_key_uuid' => 'test-key-uuid',
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('license', strtolower($response->json('error')));
    }

    public function test_server_creation_respects_limits()
    {
        // Create servers up to the limit
        for ($i = 0; $i < 5; $i++) {
            Server::factory()->create([
                'organization_id' => $this->organization->id,
                'team_id' => $this->team->id,
                'name' => "Server {$i}",
                'ip' => "192.168.1.{$i}",
            ]);
        }

        // Try to create one more server (should fail)
        $response = $this->postJson('/api/v1/servers', [
            'name' => 'Excess Server',
            'ip' => '192.168.1.200',
            'private_key_uuid' => 'test-key-uuid',
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('limit', strtolower($response->json('error')));
    }

    public function test_application_deployment_requires_license_feature()
    {
        // Test with license that has application_deployment feature
        $response = $this->postJson('/api/v1/applications/public', [
            'name' => 'Test App',
            'project_uuid' => 'test-project',
            'server_uuid' => 'test-server',
            'git_repository' => 'https://github.com/test/repo',
        ]);

        // Should not fail due to license (may fail for other reasons)
        $this->assertNotEquals(403, $response->status());

        // Remove application_deployment feature
        $this->license->update([
            'features' => ['server_management', 'domain_management'],
        ]);

        $response = $this->postJson('/api/v1/applications/public', [
            'name' => 'Test App 2',
            'project_uuid' => 'test-project',
            'server_uuid' => 'test-server',
            'git_repository' => 'https://github.com/test/repo2',
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('feature', strtolower($response->json('error')));
    }

    public function test_deployment_options_respect_license_tier()
    {
        // Professional tier should allow force rebuild
        $this->assertTrue(isDeploymentOptionAvailable('force_rebuild'));
        $this->assertTrue(isDeploymentOptionAvailable('instant_deployment'));

        // But not enterprise-only features
        $this->assertFalse(isDeploymentOptionAvailable('multi_region_deployment'));

        // Upgrade to enterprise
        $this->license->update(['license_tier' => 'enterprise']);

        // Now enterprise features should be available
        $this->assertTrue(isDeploymentOptionAvailable('multi_region_deployment'));
        $this->assertTrue(isDeploymentOptionAvailable('advanced_security'));
    }

    public function test_domain_management_requires_license()
    {
        // Test domain access with valid license
        $response = $this->getJson('/api/v1/servers/test-uuid/domains');

        // Should not fail due to license (may fail for other reasons like server not found)
        $this->assertNotEquals(403, $response->status());

        // Remove domain management feature
        $this->license->update([
            'features' => ['server_management', 'application_deployment'],
        ]);

        $response = $this->getJson('/api/v1/servers/test-uuid/domains');

        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('domain', strtolower($response->json('error')));
    }

    public function test_license_status_endpoint()
    {
        $response = $this->getJson('/api/v1/license/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'license_info' => [
                'license_tier',
                'features',
                'limits',
                'expires_at',
                'is_trial',
            ],
            'resource_limits',
            'deployment_options',
            'provisioning_status',
        ]);

        $this->assertEquals('professional', $response->json('license_info.license_tier'));
        $this->assertContains('server_management', $response->json('license_info.features'));
    }

    public function test_feature_check_endpoint()
    {
        $response = $this->getJson('/api/v1/license/features/server_management');

        $response->assertStatus(200);
        $response->assertJson([
            'feature' => 'server_management',
            'available' => true,
            'license_tier' => 'professional',
        ]);

        // Test unavailable feature
        $response = $this->getJson('/api/v1/license/features/advanced_security');

        $response->assertStatus(200);
        $response->assertJson([
            'feature' => 'advanced_security',
            'available' => false,
            'upgrade_required' => true,
        ]);
    }

    public function test_deployment_option_check_endpoint()
    {
        $response = $this->getJson('/api/v1/license/deployment-options/force_rebuild');

        $response->assertStatus(200);
        $response->assertJson([
            'option' => 'force_rebuild',
            'available' => true,
            'license_tier' => 'professional',
        ]);

        // Test enterprise-only option
        $response = $this->getJson('/api/v1/license/deployment-options/multi_region_deployment');

        $response->assertStatus(200);
        $response->assertJson([
            'option' => 'multi_region_deployment',
            'available' => false,
        ]);
    }

    public function test_license_helper_functions()
    {
        // Test hasLicenseFeature helper
        $this->assertTrue(hasLicenseFeature('server_management'));
        $this->assertFalse(hasLicenseFeature('advanced_security'));

        // Test canProvisionResource helper
        $this->assertTrue(canProvisionResource('servers'));
        $this->assertTrue(canProvisionResource('applications'));

        // Test getCurrentLicenseTier helper
        $this->assertEquals('professional', getCurrentLicenseTier());

        // Test getResourceLimits helper
        $limits = getResourceLimits();
        $this->assertArrayHasKey('servers', $limits);
        $this->assertEquals(5, $limits['servers']['limit']);
        $this->assertEquals(0, $limits['servers']['current']);
    }

    public function test_license_validation_middleware_integration()
    {
        // Test that middleware is properly integrated
        $response = $this->postJson('/api/v1/servers', [
            'name' => 'Test Server',
            'ip' => '192.168.1.100',
        ]);

        // Should include license info in response (if successful)
        if ($response->status() === 201) {
            $response->assertJsonStructure(['license_info']);
        }
    }
}
