<?php

namespace Database\Seeders;

use App\Models\CloudProviderCredential;
use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Models\TerraformDeployment;
use App\Models\User;
use App\Models\WhiteLabelConfig;
use Illuminate\Database\Seeder;

class EnterpriseTestSeeder extends Seeder
{
    /**
     * Run the database seeds for testing enterprise features.
     */
    public function run(): void
    {
        // Create test organizations with hierarchy
        $topBranch = Organization::factory()->topBranch()->create([
            'name' => 'Test Top Branch Organization',
        ]);

        $masterBranch = Organization::factory()->masterBranch()->withParent($topBranch)->create([
            'name' => 'Test Master Branch Organization',
        ]);

        $subUser = Organization::factory()->subUser()->withParent($masterBranch)->create([
            'name' => 'Test Sub User Organization',
        ]);

        $endUser = Organization::factory()->endUser()->withParent($subUser)->create([
            'name' => 'Test End User Organization',
        ]);

        // Create test users
        $adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'current_organization_id' => $topBranch->id,
        ]);

        $memberUser = User::factory()->create([
            'email' => 'member@test.com',
            'current_organization_id' => $masterBranch->id,
        ]);

        // Attach users to organizations
        $topBranch->users()->attach($adminUser->id, [
            'role' => 'owner',
            'permissions' => [],
            'is_active' => true,
        ]);

        $masterBranch->users()->attach($memberUser->id, [
            'role' => 'admin',
            'permissions' => ['manage_servers', 'deploy_applications'],
            'is_active' => true,
        ]);

        // Create enterprise licenses
        EnterpriseLicense::factory()->create([
            'organization_id' => $topBranch->id,
            'license_tier' => 'enterprise',
            'features' => [
                'infrastructure_provisioning',
                'domain_management',
                'white_label_branding',
                'api_access',
                'payment_processing',
            ],
            'limits' => [
                'max_users' => 100,
                'max_servers' => 500,
                'max_domains' => 50,
            ],
        ]);

        EnterpriseLicense::factory()->trial()->create([
            'organization_id' => $masterBranch->id,
            'license_tier' => 'professional',
            'features' => [
                'infrastructure_provisioning',
                'white_label_branding',
            ],
            'limits' => [
                'max_users' => 10,
                'max_servers' => 50,
                'max_domains' => 5,
            ],
        ]);

        // Create white label configs
        WhiteLabelConfig::factory()->create([
            'organization_id' => $topBranch->id,
            'platform_name' => 'Enterprise Cloud Platform',
            'theme_config' => [
                'primary_color' => '#1f2937',
                'secondary_color' => '#3b82f6',
                'accent_color' => '#10b981',
            ],
        ]);

        // Create cloud provider credentials
        CloudProviderCredential::factory()->aws()->create([
            'organization_id' => $topBranch->id,
            'credential_name' => 'Test AWS Credentials',
        ]);

        CloudProviderCredential::factory()->gcp()->create([
            'organization_id' => $topBranch->id,
            'credential_name' => 'Test GCP Credentials',
        ]);

        CloudProviderCredential::factory()->digitalocean()->create([
            'organization_id' => $masterBranch->id,
            'credential_name' => 'Test DigitalOcean Credentials',
        ]);

        // Create terraform deployments
        TerraformDeployment::factory()->completed()->create([
            'organization_id' => $topBranch->id,
            'deployment_name' => 'Production Infrastructure',
            'provider_type' => 'aws',
        ]);

        TerraformDeployment::factory()->provisioning()->create([
            'organization_id' => $masterBranch->id,
            'deployment_name' => 'Staging Infrastructure',
            'provider_type' => 'digitalocean',
        ]);

        TerraformDeployment::factory()->failed()->create([
            'organization_id' => $masterBranch->id,
            'deployment_name' => 'Failed Deployment',
            'provider_type' => 'aws',
            'error_message' => 'Invalid credentials provided',
        ]);
    }
}
