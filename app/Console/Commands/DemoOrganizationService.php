<?php

namespace App\Console\Commands;

use App\Contracts\OrganizationServiceInterface;
use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoOrganizationService extends Command
{
    protected $signature = 'demo:organization-service';

    protected $description = 'Demonstrate the OrganizationService functionality';

    public function handle()
    {
        $this->info('🚀 Demonstrating OrganizationService functionality...');

        $organizationService = app(OrganizationServiceInterface::class);

        DB::transaction(function () use ($organizationService) {
            // 1. Create a top branch organization
            $this->info('📁 Creating Top Branch organization...');
            $topBranch = $organizationService->createOrganization([
                'name' => 'Acme Corporation',
                'hierarchy_type' => 'top_branch',
            ]);
            $this->line("✅ Created: {$topBranch->name} (ID: {$topBranch->id})");

            // 2. Create a master branch under the top branch
            $this->info('📂 Creating Master Branch organization...');
            $masterBranch = $organizationService->createOrganization([
                'name' => 'Acme Hosting Division',
                'hierarchy_type' => 'master_branch',
            ], $topBranch);
            $this->line("✅ Created: {$masterBranch->name} (Parent: {$masterBranch->parent->name})");

            // 3. Create a sub user under the master branch
            $this->info('📄 Creating Sub User organization...');
            $subUser = $organizationService->createOrganization([
                'name' => 'Client Services Team',
                'hierarchy_type' => 'sub_user',
            ], $masterBranch);
            $this->line("✅ Created: {$subUser->name} (Level: {$subUser->hierarchy_level})");

            // 4. Create an end user under the sub user
            $this->info('👤 Creating End User organization...');
            $endUser = $organizationService->createOrganization([
                'name' => 'Customer ABC Inc',
                'hierarchy_type' => 'end_user',
            ], $subUser);
            $this->line("✅ Created: {$endUser->name} (Level: {$endUser->hierarchy_level})");

            // 5. Create some users and attach them to organizations
            $this->info('👥 Creating users and assigning roles...');

            $owner = User::factory()->create(['name' => 'John Owner', 'email' => 'owner@acme.com']);
            $admin = User::factory()->create(['name' => 'Jane Admin', 'email' => 'admin@acme.com']);
            $member = User::factory()->create(['name' => 'Bob Member', 'email' => 'member@acme.com']);

            $organizationService->attachUserToOrganization($topBranch, $owner, 'owner');
            $organizationService->attachUserToOrganization($topBranch, $admin, 'admin');
            $organizationService->attachUserToOrganization($masterBranch, $member, 'member');

            $this->line('✅ Attached users to organizations');

            // 6. Create a license for the top branch
            $this->info('📜 Creating enterprise license...');
            $license = EnterpriseLicense::factory()->create([
                'organization_id' => $topBranch->id,
                'features' => [
                    'infrastructure_provisioning',
                    'domain_management',
                    'white_label_branding',
                    'payment_processing',
                ],
                'limits' => [
                    'max_users' => 50,
                    'max_servers' => 100,
                    'max_domains' => 25,
                ],
            ]);
            $this->line("✅ Created license: {$license->license_key}");

            // 7. Test permission checking
            $this->info('🔐 Testing permission system...');

            $canOwnerDelete = $organizationService->canUserPerformAction($owner, $topBranch, 'delete_organization');
            $canAdminDelete = $organizationService->canUserPerformAction($admin, $topBranch, 'delete_organization');
            $canMemberView = $organizationService->canUserPerformAction($member, $masterBranch, 'view_servers');

            $this->line('✅ Owner can delete org: '.($canOwnerDelete ? 'Yes' : 'No'));
            $this->line('✅ Admin can delete org: '.($canAdminDelete ? 'Yes' : 'No'));
            $this->line('✅ Member can view servers: '.($canMemberView ? 'Yes' : 'No'));

            // 8. Test organization switching
            $this->info('🔄 Testing organization switching...');
            $organizationService->switchUserOrganization($owner, $topBranch);
            $owner->refresh();
            $this->line("✅ Owner switched to: {$owner->currentOrganization->name}");

            // 9. Get organization hierarchy
            $this->info('🌳 Building organization hierarchy...');
            $hierarchy = $organizationService->getOrganizationHierarchy($topBranch);
            $this->displayHierarchy($hierarchy);

            // 10. Get usage statistics
            $this->info('📊 Getting usage statistics...');
            $usage = $organizationService->getOrganizationUsage($topBranch);
            $this->line('✅ Top Branch Usage:');
            $this->line("   - Users: {$usage['users']}");
            $this->line("   - Servers: {$usage['servers']}");
            $this->line("   - Applications: {$usage['applications']}");
            $this->line("   - Children: {$usage['children']}");

            // 11. Test moving organization
            $this->info('📦 Testing organization move...');
            $newTopBranch = $organizationService->createOrganization([
                'name' => 'New Parent Corp',
                'hierarchy_type' => 'top_branch',
            ]);

            $movedOrg = $organizationService->moveOrganization($masterBranch, $newTopBranch);
            $this->line("✅ Moved '{$movedOrg->name}' to '{$movedOrg->parent->name}'");

            // 12. Test user role updates
            $this->info('🔧 Testing role updates...');
            $organizationService->updateUserRole($topBranch, $admin, 'member', ['view_servers', 'deploy_applications']);
            $this->line('✅ Updated admin role to member with custom permissions');

            // 13. Get accessible organizations for a user
            $this->info('Getting user accessible organizations...');
            $userOrgs = $organizationService->getUserOrganizations($owner);
            $this->line("✅ Owner has access to {$userOrgs->count()} organizations:");
            foreach ($userOrgs as $org) {
                $this->line("   - {$org->name} ({$org->hierarchy_type})");
            }

            $this->info('🎉 OrganizationService demonstration completed successfully!');

            // Clean up (rollback transaction)
            throw new \Exception('Rolling back demo data...');
        });

        $this->info('🧹 Demo data cleaned up (transaction rolled back)');

        return 0;
    }

    private function displayHierarchy(array $hierarchy, int $indent = 0)
    {
        $prefix = str_repeat('  ', $indent);
        $this->line("{$prefix}📁 {$hierarchy['name']} ({$hierarchy['hierarchy_type']}) - {$hierarchy['user_count']} users");

        foreach ($hierarchy['children'] as $child) {
            $this->displayHierarchy($child, $indent + 1);
        }
    }
}
