<?php

namespace App\Http\Controllers\Api;

use App\Contracts\OrganizationServiceInterface;
use App\Helpers\OrganizationContext;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{
    protected $organizationService;

    public function __construct(OrganizationServiceInterface $organizationService)
    {
        $this->organizationService = $organizationService;
    }

    public function index()
    {
        try {
            $currentOrganization = OrganizationContext::current();
            $organizations = $this->getAccessibleOrganizations();
            $hierarchyTypes = $this->getHierarchyTypes();
            $availableParents = $this->getAvailableParents();

            return response()->json([
                'organizations' => $organizations,
                'currentOrganization' => $currentOrganization,
                'hierarchyTypes' => $hierarchyTypes,
                'availableParents' => $availableParents,
            ]);
        } catch (\Exception $e) {
            \Log::error('Organization index error: '.$e->getMessage());

            // Return basic data even if there's an error
            return response()->json([
                'organizations' => [],
                'currentOrganization' => null,
                'hierarchyTypes' => [],
                'availableParents' => [],
            ]);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'hierarchy_type' => 'required|in:top_branch,master_branch,sub_user,end_user',
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'is_active' => 'boolean',
        ]);

        try {
            $parent = $request->parent_organization_id
                ? Organization::find($request->parent_organization_id)
                : null;

            $organization = $this->organizationService->createOrganization([
                'name' => $request->name,
                'hierarchy_type' => $request->hierarchy_type,
                'is_active' => $request->is_active ?? true,
                'owner_id' => Auth::id(),
            ], $parent);

            return response()->json([
                'message' => 'Organization created successfully',
                'organization' => $organization,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create organization: '.$e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, Organization $organization)
    {
        if (! OrganizationContext::can('manage_organization', $organization)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        try {
            $this->organizationService->updateOrganization($organization, [
                'name' => $request->name,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'message' => 'Organization updated successfully',
                'organization' => $organization->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update organization: '.$e->getMessage(),
            ], 400);
        }
    }

    public function switchOrganization(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
        ]);

        try {
            $organization = Organization::findOrFail($request->organization_id);
            $this->organizationService->switchUserOrganization(Auth::user(), $organization);

            return response()->json([
                'message' => 'Switched to '.$organization->name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to switch organization: '.$e->getMessage(),
            ], 400);
        }
    }

    public function hierarchy(Organization $organization)
    {
        if (! OrganizationContext::can('view_organization', $organization)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $hierarchy = $this->organizationService->getOrganizationHierarchy($organization);

            return response()->json([
                'hierarchy' => $hierarchy,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load hierarchy: '.$e->getMessage(),
            ], 400);
        }
    }

    public function users(Organization $organization)
    {
        if (! OrganizationContext::can('view_organization', $organization)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = $organization->users()->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'permissions' => $user->pivot->permissions ?? [],
                'is_active' => $user->pivot->is_active,
            ];
        });

        return response()->json([
            'users' => $users,
        ]);
    }

    public function addUser(Request $request, Organization $organization)
    {
        if (! OrganizationContext::can('manage_users', $organization)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:owner,admin,member,viewer',
            'permissions' => 'array',
        ]);

        try {
            $user = User::where('email', $request->email)->firstOrFail();

            // Check if user is already in organization
            if ($organization->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'message' => 'User is already a member of this organization.',
                ], 400);
            }

            $this->organizationService->attachUserToOrganization(
                $organization,
                $user,
                $request->role,
                $request->permissions ?? []
            );

            return response()->json([
                'message' => 'User added to organization successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add user: '.$e->getMessage(),
            ], 400);
        }
    }

    public function updateUser(Request $request, Organization $organization, User $user)
    {
        if (! OrganizationContext::can('manage_users', $organization)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'role' => 'required|in:owner,admin,member,viewer',
            'permissions' => 'array',
        ]);

        try {
            $this->organizationService->updateUserRole(
                $organization,
                $user,
                $request->role,
                $request->permissions ?? []
            );

            return response()->json([
                'message' => 'User updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user: '.$e->getMessage(),
            ], 400);
        }
    }

    public function removeUser(Organization $organization, User $user)
    {
        if (! OrganizationContext::can('manage_users', $organization)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->organizationService->detachUserFromOrganization($organization, $user);

            return response()->json([
                'message' => 'User removed from organization successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove user: '.$e->getMessage(),
            ], 400);
        }
    }

    public function rolesAndPermissions()
    {
        return response()->json([
            'roles' => [
                'owner' => 'Owner',
                'admin' => 'Administrator',
                'member' => 'Member',
                'viewer' => 'Viewer',
            ],
            'permissions' => [
                'view_organization' => 'View Organization',
                'edit_organization' => 'Edit Organization',
                'manage_users' => 'Manage Users',
                'view_hierarchy' => 'View Hierarchy',
                'switch_organization' => 'Switch Organization',
            ],
        ]);
    }

    protected function getAccessibleOrganizations()
    {
        $user = Auth::user();
        $userOrganizations = $this->organizationService->getUserOrganizations($user);

        // If user is owner/admin of current org, also show child organizations
        if (OrganizationContext::isAdmin()) {
            $currentOrg = OrganizationContext::current();
            if ($currentOrg) {
                $children = $currentOrg->getAllDescendants();
                $userOrganizations = $userOrganizations->merge($children);
            }
        }

        return $userOrganizations->unique('id')->values();
    }

    protected function getHierarchyTypes()
    {
        $currentOrg = OrganizationContext::current();

        if (! $currentOrg) {
            return ['end_user' => 'End User'];
        }

        $allowedTypes = [];

        switch ($currentOrg->hierarchy_type) {
            case 'top_branch':
                $allowedTypes['master_branch'] = 'Master Branch';
                break;
            case 'master_branch':
                $allowedTypes['sub_user'] = 'Sub User';
                break;
            case 'sub_user':
                $allowedTypes['end_user'] = 'End User';
                break;
        }

        return $allowedTypes;
    }

    protected function getAvailableParents()
    {
        $user = Auth::user();

        return $this->organizationService->getUserOrganizations($user)
            ->filter(function ($org) {
                $userOrg = $org->users()->where('user_id', Auth::id())->first();

                return $userOrg && in_array($userOrg->pivot->role, ['owner', 'admin']);
            })->values();
    }
}
