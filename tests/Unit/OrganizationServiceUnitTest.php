<?php

namespace Tests\Unit;

use App\Services\OrganizationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OrganizationServiceUnitTest extends TestCase
{
    protected OrganizationService $organizationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizationService = new OrganizationService;
    }

    public function test_validates_organization_data_with_invalid_hierarchy_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hierarchy type');

        $data = [
            'name' => 'Test Organization',
            'hierarchy_type' => 'invalid_type',
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->organizationService);
        $method = $reflection->getMethod('validateOrganizationData');
        $method->setAccessible(true);
        $method->invoke($this->organizationService, $data);
    }

    public function test_validates_hierarchy_creation_rules()
    {
        $reflection = new \ReflectionClass($this->organizationService);
        $method = $reflection->getMethod('validateHierarchyCreation');
        $method->setAccessible(true);

        // Create mock parent organization
        $parent = $this->createMock(\App\Models\Organization::class);
        $parent->method('getAttribute')->with('hierarchy_type')->willReturn('end_user');
        $parent->hierarchy_type = 'end_user';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A end_user cannot have a master_branch as a child');

        $method->invoke($this->organizationService, $parent, 'master_branch');
    }

    public function test_validates_role_with_invalid_role()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role');

        $reflection = new \ReflectionClass($this->organizationService);
        $method = $reflection->getMethod('validateRole');
        $method->setAccessible(true);
        $method->invoke($this->organizationService, 'invalid_role');
    }

    public function test_validates_role_with_valid_roles()
    {
        $reflection = new \ReflectionClass($this->organizationService);
        $method = $reflection->getMethod('validateRole');
        $method->setAccessible(true);

        $validRoles = ['owner', 'admin', 'member', 'viewer'];

        foreach ($validRoles as $role) {
            // Should not throw exception
            $method->invoke($this->organizationService, $role);
            $this->assertTrue(true); // Assert that we got here without exception
        }
    }

    public function test_checks_role_permissions_correctly()
    {
        $reflection = new \ReflectionClass($this->organizationService);
        $method = $reflection->getMethod('checkRolePermission');
        $method->setAccessible(true);

        // Owner can do everything
        $this->assertTrue(
            $method->invoke($this->organizationService, 'owner', [], 'delete_organization')
        );

        // Admin cannot delete organization
        $this->assertFalse(
            $method->invoke($this->organizationService, 'admin', [], 'delete_organization')
        );

        // Admin can manage servers
        $this->assertTrue(
            $method->invoke($this->organizationService, 'admin', [], 'manage_servers')
        );

        // Member has limited permissions
        $this->assertTrue(
            $method->invoke($this->organizationService, 'member', [], 'view_servers')
        );

        $this->assertFalse(
            $method->invoke($this->organizationService, 'member', [], 'delete_organization')
        );

        // Viewer can only view
        $this->assertTrue(
            $method->invoke($this->organizationService, 'viewer', [], 'view_servers')
        );

        $this->assertFalse(
            $method->invoke($this->organizationService, 'viewer', [], 'deploy_applications')
        );

        // Custom permissions work
        $this->assertTrue(
            $method->invoke($this->organizationService, 'custom', ['special_action'], 'special_action')
        );

        $this->assertFalse(
            $method->invoke($this->organizationService, 'custom', ['special_action'], 'other_action')
        );
    }

    public function test_detects_circular_dependency()
    {
        $reflection = new \ReflectionClass($this->organizationService);
        $method = $reflection->getMethod('wouldCreateCircularDependency');
        $method->setAccessible(true);

        // Create mock organizations with proper property setup
        $org1 = $this->createMock(\App\Models\Organization::class);
        $org1->id = 'org1';
        $org1->method('getAttribute')->with('id')->willReturn('org1');

        $org2 = $this->createMock(\App\Models\Organization::class);
        $org2->id = 'org2';
        $org2->method('getAttribute')->willReturnMap([
            ['id', 'org2'],
            ['parent', $org1],
        ]);

        $org3 = $this->createMock(\App\Models\Organization::class);
        $org3->id = 'org3';
        $org3->method('getAttribute')->willReturnMap([
            ['id', 'org3'],
            ['parent', $org2],
        ]);

        // Set up parent relationships
        $org3->parent = $org2;
        $org2->parent = $org1;
        $org1->parent = null;

        // Moving org1 under org3 would create circular dependency
        $this->assertTrue(
            $method->invoke($this->organizationService, $org1, $org3)
        );

        // Moving org3 under a new parent (no circular dependency)
        $newParent = $this->createMock(\App\Models\Organization::class);
        $newParent->id = 'new_parent';
        $newParent->parent = null;
        $newParent->method('getAttribute')->willReturnMap([
            ['id', 'new_parent'],
            ['parent', null],
        ]);

        $this->assertFalse(
            $method->invoke($this->organizationService, $org3, $newParent)
        );
    }

    public function test_builds_hierarchy_tree_structure()
    {
        $reflection = new \ReflectionClass($this->organizationService);
        $method = $reflection->getMethod('buildHierarchyTree');
        $method->setAccessible(true);

        // Create mock organization without children for simplicity
        $parentOrg = $this->createMock(\App\Models\Organization::class);
        $parentOrg->method('getAttribute')->willReturnMap([
            ['id', 'parent1'],
            ['name', 'Parent Organization'],
            ['hierarchy_type', 'top_branch'],
            ['hierarchy_level', 0],
            ['is_active', true],
        ]);

        // Set properties directly
        $parentOrg->id = 'parent1';
        $parentOrg->name = 'Parent Organization';
        $parentOrg->hierarchy_type = 'top_branch';
        $parentOrg->hierarchy_level = 0;
        $parentOrg->is_active = true;

        // Mock empty children collection
        $emptyCollection = $this->createMock(\Illuminate\Database\Eloquent\Collection::class);
        $emptyCollection->method('map')->willReturn(collect([]));

        $parentOrg->method('children')->willReturn($emptyCollection);

        // Mock users relation with a simpler mock that returns 5 for count
        $usersRelation = $this->createMock(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        $usersRelation->method('wherePivot')->willReturnSelf();

        // Create a mock that has a count method
        $countableRelation = new class
        {
            public function count()
            {
                return 5;
            }
        };

        $usersRelation->method('count')->willReturn(5);
        $parentOrg->method('users')->willReturn($usersRelation);

        $result = $method->invoke($this->organizationService, $parentOrg);

        $this->assertEquals('parent1', $result['id']);
        $this->assertEquals('Parent Organization', $result['name']);
        $this->assertEquals('top_branch', $result['hierarchy_type']);
        $this->assertEquals(0, $result['hierarchy_level']);
        $this->assertEquals(5, $result['user_count']);
        $this->assertTrue($result['is_active']);
        $this->assertCount(0, $result['children']);
    }
}
