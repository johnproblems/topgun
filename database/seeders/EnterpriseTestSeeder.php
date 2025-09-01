<?php

namespace Database\Seeders;

use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Models\User;
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

        // Use existing test users or create new ones
        $adminUser = User::where('email', 'test@example.com')->first();
        if (! $adminUser) {
            $adminUser = User::factory()->create([
                'email' => 'admin@test.com',
                'current_organization_id' => $topBranch->id,
            ]);
        } else {
            $adminUser->update(['current_organization_id' => $topBranch->id]);
        }

        $memberUser = User::where('email', 'test2@example.com')->first();
        if (! $memberUser) {
            $memberUser = User::factory()->create([
                'email' => 'member@test.com',
                'current_organization_id' => $masterBranch->id,
            ]);
        } else {
            $memberUser->update(['current_organization_id' => $masterBranch->id]);
        }

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

        // Note: White label configs, cloud provider credentials, and terraform deployments
        // will be added in future iterations as those features are implemented
    }
}
