<?php

namespace App\Livewire\Organization;

use App\Contracts\OrganizationServiceInterface;
use App\Helpers\OrganizationContext;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class OrganizationManager extends Component
{
    use WithPagination;

    public $showCreateForm = false;

    public $showEditForm = false;

    public $showUserManagement = false;

    public $showHierarchyView = false;

    public $selectedOrganization = null;

    // Form properties
    public $name = '';

    public $hierarchy_type = 'end_user';

    public $parent_organization_id = null;

    public $is_active = true;

    // User management properties
    public $selectedUser = null;

    public $userRole = 'member';

    public $userPermissions = [];

    protected $rules = [
        'name' => 'required|string|max:255',
        'hierarchy_type' => 'required|in:top_branch,master_branch,sub_user,end_user',
        'parent_organization_id' => 'nullable|exists:organizations,id',
        'is_active' => 'boolean',
    ];

    public function mount()
    {
        // Ensure user has permission to manage organizations
        if (! OrganizationContext::can('manage_organizations')) {
            abort(403, 'You do not have permission to manage organizations.');
        }
    }

    public function render()
    {
        $currentOrganization = OrganizationContext::current();

        // Get organizations based on user's hierarchy level
        $organizations = $this->getAccessibleOrganizations();

        $users = $this->selectedOrganization
            ? $this->selectedOrganization->users()->paginate(10, ['*'], 'users')
            : collect();

        return view('livewire.organization.organization-manager', [
            'organizations' => $organizations,
            'users' => $users,
            'currentOrganization' => $currentOrganization,
            'hierarchyTypes' => $this->getHierarchyTypes(),
            'availableParents' => $this->getAvailableParents(),
        ]);
    }

    public function createOrganization()
    {
        $this->validate();

        try {
            $organizationService = app(OrganizationServiceInterface::class);

            $parent = $this->parent_organization_id
                ? Organization::find($this->parent_organization_id)
                : null;

            $organization = $organizationService->createOrganization([
                'name' => $this->name,
                'hierarchy_type' => $this->hierarchy_type,
                'is_active' => $this->is_active,
                'owner_id' => Auth::id(),
            ], $parent);

            $this->resetForm();
            $this->showCreateForm = false;

            session()->flash('success', 'Organization created successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create organization: '.$e->getMessage());
        }
    }

    public function editOrganization(Organization $organization)
    {
        // Check permissions
        if (! OrganizationContext::can('manage_organization', $organization)) {
            session()->flash('error', 'You do not have permission to edit this organization.');

            return;
        }

        $this->selectedOrganization = $organization;
        $this->name = $organization->name;
        $this->hierarchy_type = $organization->hierarchy_type;
        $this->parent_organization_id = $organization->parent_organization_id;
        $this->is_active = $organization->is_active;
        $this->showEditForm = true;
    }

    public function updateOrganization()
    {
        $this->validate();

        try {
            $organizationService = app(OrganizationServiceInterface::class);

            $organizationService->updateOrganization($this->selectedOrganization, [
                'name' => $this->name,
                'hierarchy_type' => $this->hierarchy_type,
                'parent_organization_id' => $this->parent_organization_id,
                'is_active' => $this->is_active,
            ]);

            $this->resetForm();
            $this->showEditForm = false;

            session()->flash('success', 'Organization updated successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update organization: '.$e->getMessage());
        }
    }

    public function switchToOrganization(Organization $organization)
    {
        try {
            $organizationService = app(OrganizationServiceInterface::class);
            $organizationService->switchUserOrganization(Auth::user(), $organization);

            session()->flash('success', 'Switched to '.$organization->name);

            return redirect()->to('/dashboard');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to switch organization: '.$e->getMessage());
        }
    }

    public function manageUsers(Organization $organization)
    {
        if (! OrganizationContext::can('manage_users', $organization)) {
            session()->flash('error', 'You do not have permission to manage users for this organization.');

            return;
        }

        $this->selectedOrganization = $organization;
        $this->showUserManagement = true;
    }

    public function addUserToOrganization()
    {
        $this->validate([
            'selectedUser' => 'required|exists:users,id',
            'userRole' => 'required|in:owner,admin,member,viewer',
        ]);

        try {
            $organizationService = app(OrganizationServiceInterface::class);
            $user = User::find($this->selectedUser);

            $organizationService->attachUserToOrganization(
                $this->selectedOrganization,
                $user,
                $this->userRole,
                $this->userPermissions
            );

            $this->selectedUser = null;
            $this->userRole = 'member';
            $this->userPermissions = [];

            session()->flash('success', 'User added to organization successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add user: '.$e->getMessage());
        }
    }

    public function removeUserFromOrganization(User $user)
    {
        try {
            $organizationService = app(OrganizationServiceInterface::class);

            $organizationService->detachUserFromOrganization($this->selectedOrganization, $user);

            session()->flash('success', 'User removed from organization successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to remove user: '.$e->getMessage());
        }
    }

    public function viewHierarchy(Organization $organization)
    {
        if (! OrganizationContext::can('view_organization', $organization)) {
            session()->flash('error', 'You do not have permission to view this organization hierarchy.');

            return;
        }

        $this->selectedOrganization = $organization;
        $this->showHierarchyView = true;
    }

    public function getOrganizationHierarchy(Organization $organization)
    {
        $organizationService = app(OrganizationServiceInterface::class);

        return $organizationService->getOrganizationHierarchy($organization);
    }

    public function getOrganizationUsage(Organization $organization)
    {
        $organizationService = app(OrganizationServiceInterface::class);

        return $organizationService->getOrganizationUsage($organization);
    }

    public function deleteOrganization(Organization $organization)
    {
        if (! OrganizationContext::can('delete_organization', $organization)) {
            session()->flash('error', 'You do not have permission to delete this organization.');

            return;
        }

        try {
            $organizationService = app(OrganizationServiceInterface::class);
            $organizationService->deleteOrganization($organization);

            session()->flash('success', 'Organization deleted successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete organization: '.$e->getMessage());
        }
    }

    public function updateUserRole(User $user, string $newRole)
    {
        $this->validate([
            'newRole' => 'required|in:owner,admin,member,viewer',
        ]);

        try {
            $organizationService = app(OrganizationServiceInterface::class);

            $organizationService->updateUserRole(
                $this->selectedOrganization,
                $user,
                $newRole
            );

            session()->flash('success', 'User role updated successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user role: '.$e->getMessage());
        }
    }

    protected function getAccessibleOrganizations()
    {
        $organizationService = app(OrganizationServiceInterface::class);
        $user = Auth::user();

        // Get all organizations the user has access to
        $userOrganizations = $organizationService->getUserOrganizations($user);

        // If user is owner/admin of current org, also show child organizations
        if (OrganizationContext::isAdmin()) {
            $currentOrg = OrganizationContext::current();
            if ($currentOrg) {
                $children = $currentOrg->getAllDescendants();
                $userOrganizations = $userOrganizations->merge($children);
            }
        }

        return $userOrganizations->unique('id');
    }

    protected function getHierarchyTypes()
    {
        $currentOrg = OrganizationContext::current();

        if (! $currentOrg) {
            return ['end_user' => 'End User'];
        }

        // Based on current organization type, determine what can be created
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
        $organizationService = app(OrganizationServiceInterface::class);

        return $organizationService->getUserOrganizations($user)
            ->filter(function ($org) {
                // Can only create children if user is owner/admin
                $userOrg = $org->users()->where('user_id', Auth::id())->first();

                return $userOrg && in_array($userOrg->pivot->role, ['owner', 'admin']);
            });
    }

    protected function resetForm()
    {
        $this->name = '';
        $this->hierarchy_type = 'end_user';
        $this->parent_organization_id = null;
        $this->is_active = true;
        $this->selectedOrganization = null;
    }

    public function openCreateForm()
    {
        $this->showCreateForm = true;
    }

    public function closeModals()
    {
        $this->showCreateForm = false;
        $this->showEditForm = false;
        $this->showUserManagement = false;
        $this->showHierarchyView = false;
        $this->resetForm();
    }
}
