<?php

namespace Tests\Unit;

use App\Contracts\OrganizationServiceInterface;
use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class OrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationServiceInterface $organizationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizationService = new OrganizationService;
    }

    public function test_creates_organization_successfully()
    {
        $data = [
            'name' => 'Test Organization',
            'hierarchy_type' => 'top_branch',
        ];

        $organization = $this->organizationService->createOrganization($data);

        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertEquals('Test Organization', $organization->name);
        $this->assertEquals('top_branch', $organization->hierarchy_type);
        $this->assertEquals(0, $organization->hierarchy_level);
        $this->assertNull($organization->parent_organization_id);
    }

    public function test_creates_child_organization_with_proper_hierarchy()
    {
        $parent = Organization::factory()->create([
            'hierarchy_type' => 'top_branch',
            'hierarchy_level' => 0,
        ]);

        $data = [
            'name' => 'Child Organization',
            'hierarchy_type' => 'master_branch',
        ];

        $child = $this->organizationService->createOrganization($data, $parent);

        $this->assertEquals($parent->id, $child->parent_organization_id);
        $this->assertEquals(1, $child->hierarchy_level);
        $this->assertEquals('master_branch', $child->hierarchy_type);
    }

    public function test_validates_hierarchy_creation_rules()
    {
        $parent = Organization::factory()->create([
            'hierarchy_type' => 'end_user',
        ]);

        $data = [
            'name' => 'Invalid Child',
            'hierarchy_type' => 'master_branch',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A end_user cannot have a master_branch as a child');

        $this->organizationService->createOrganization($data, $parent);
    }

    public function test_attaches_user_to_organization()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $user, 'admin');

        $this->assertTrue($organization->users()->where('user_id', $user->id)->exists());

        $userOrg = $organization->users()->where('user_id', $user->id)->first();
        $this->assertEquals('admin', $userOrg->pivot->role);
        $this->assertTrue($userOrg->pivot->is_active);
    }

    public function test_prevents_duplicate_user_attachment()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $user, 'admin');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User is already in this organization');

        $this->organizationService->attachUserToOrganization($organization, $user, 'member');
    }

    public function test_updates_user_role()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $user, 'member');
        $this->organizationService->updateUserRole($organization, $user, 'admin', ['manage_servers']);

        $userOrg = $organization->users()->where('user_id', $user->id)->first();
        $this->assertEquals('admin', $userOrg->pivot->role);
        $this->assertEquals(['manage_servers'], $userOrg->pivot->permissions);
    }

    public function test_switches_user_organization()
    {
        $user = User::factory()->create();
        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();

        $this->organizationService->attachUserToOrganization($organization1, $user, 'member');
        $this->organizationService->attachUserToOrganization($organization2, $user, 'admin');

        $this->organizationService->switchUserOrganization($user, $organization2);

        $user->refresh();
        $this->assertEquals($organization2->id, $user->current_organization_id);
    }

    public function test_prevents_switching_to_unauthorized_organization()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User does not have access to this organization');

        $this->organizationService->switchUserOrganization($user, $organization);
    }

    public function test_checks_user_permissions_correctly()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $user, 'owner');

        // Owner can do everything
        $this->assertTrue(
            $this->organizationService->canUserPerformAction($user, $organization, 'delete_organization')
        );

        // Update to admin role
        $this->organizationService->updateUserRole($organization, $user, 'admin');

        // Admin cannot delete organization
        $this->assertFalse(
            $this->organizationService->canUserPerformAction($user, $organization, 'delete_organization')
        );

        // But admin can manage servers
        $this->assertTrue(
            $this->organizationService->canUserPerformAction($user, $organization, 'manage_servers')
        );
    }

    public function test_prevents_removing_last_owner()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $user, 'owner');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove the last owner from an organization');

        $this->organizationService->detachUserFromOrganization($organization, $user);
    }

    public function test_allows_removing_non_last_owner()
    {
        $organization = Organization::factory()->create();
        $owner1 = User::factory()->create();
        $owner2 = User::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $owner1, 'owner');
        $this->organizationService->attachUserToOrganization($organization, $owner2, 'owner');

        $this->organizationService->detachUserFromOrganization($organization, $owner1);

        $this->assertFalse($organization->users()->where('user_id', $owner1->id)->exists());
        $this->assertTrue($organization->users()->where('user_id', $owner2->id)->exists());
    }

    public function test_moves_organization_with_validation()
    {
        $topBranch = Organization::factory()->create([
            'hierarchy_type' => 'top_branch',
            'hierarchy_level' => 0,
        ]);

        $masterBranch = Organization::factory()->create([
            'hierarchy_type' => 'master_branch',
            'hierarchy_level' => 1,
            'parent_organization_id' => $topBranch->id,
        ]);

        $newTopBranch = Organization::factory()->create([
            'hierarchy_type' => 'top_branch',
            'hierarchy_level' => 0,
        ]);

        $movedOrg = $this->organizationService->moveOrganization($masterBranch, $newTopBranch);

        $this->assertEquals($newTopBranch->id, $movedOrg->parent_organization_id);
        $this->assertEquals(1, $movedOrg->hierarchy_level);
    }

    public function test_prevents_circular_dependency_in_move()
    {
        $parent = Organization::factory()->create([
            'hierarchy_type' => 'top_branch',
            'hierarchy_level' => 0,
        ]);

        $child = Organization::factory()->create([
            'hierarchy_type' => 'master_branch',
            'hierarchy_level' => 1,
            'parent_organization_id' => $parent->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Moving organization would create circular dependency');

        $this->organizationService->moveOrganization($parent, $child);
    }

    public function test_gets_user_organizations()
    {
        $user = User::factory()->create();
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $org3 = Organization::factory()->create();

        $this->organizationService->attachUserToOrganization($org1, $user, 'owner');
        $this->organizationService->attachUserToOrganization($org2, $user, 'admin');
        // Don't attach to org3

        $userOrgs = $this->organizationService->getUserOrganizations($user);

        $this->assertCount(2, $userOrgs);
        $this->assertTrue($userOrgs->contains('id', $org1->id));
        $this->assertTrue($userOrgs->contains('id', $org2->id));
        $this->assertFalse($userOrgs->contains('id', $org3->id));
    }

    public function test_builds_organization_hierarchy()
    {
        $topBranch = Organization::factory()->create([
            'name' => 'Top Branch',
            'hierarchy_type' => 'top_branch',
            'hierarchy_level' => 0,
        ]);

        $masterBranch = Organization::factory()->create([
            'name' => 'Master Branch',
            'hierarchy_type' => 'master_branch',
            'hierarchy_level' => 1,
            'parent_organization_id' => $topBranch->id,
        ]);

        $subUser = Organization::factory()->create([
            'name' => 'Sub User',
            'hierarchy_type' => 'sub_user',
            'hierarchy_level' => 2,
            'parent_organization_id' => $masterBranch->id,
        ]);

        $hierarchy = $this->organizationService->getOrganizationHierarchy($topBranch);

        $this->assertEquals('Top Branch', $hierarchy['name']);
        $this->assertEquals('top_branch', $hierarchy['hierarchy_type']);
        $this->assertCount(1, $hierarchy['children']);

        $masterChild = $hierarchy['children'][0];
        $this->assertEquals('Master Branch', $masterChild['name']);
        $this->assertCount(1, $masterChild['children']);

        $subChild = $masterChild['children'][0];
        $this->assertEquals('Sub User', $subChild['name']);
        $this->assertCount(0, $subChild['children']);
    }

    public function test_gets_organization_usage()
    {
        $organization = Organization::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $user1, 'owner');
        $this->organizationService->attachUserToOrganization($organization, $user2, 'member');

        $usage = $this->organizationService->getOrganizationUsage($organization);

        $this->assertEquals(2, $usage['users']);
        $this->assertEquals(0, $usage['servers']); // No servers created in test
        $this->assertEquals(0, $usage['applications']); // No applications created in test
        $this->assertEquals(0, $usage['children']); // No child organizations
    }

    public function test_respects_license_limits_for_user_attachment()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'limits' => ['max_users' => 1],
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // First user should succeed
        $this->organizationService->attachUserToOrganization($organization, $user1, 'owner');

        // Second user should fail due to license limit
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Organization has reached maximum user limit');

        $this->organizationService->attachUserToOrganization($organization, $user2, 'member');
    }

    public function test_caches_user_organizations()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->organizationService->attachUserToOrganization($organization, $user, 'owner');

        // First call should cache the result
        $orgs1 = $this->organizationService->getUserOrganizations($user);

        // Second call should use cache
        $orgs2 = $this->organizationService->getUserOrganizations($user);

        $this->assertEquals($orgs1->count(), $orgs2->count());

        // Verify cache key exists
        $this->assertTrue(Cache::has("user_organizations_{$user->id}"));
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
