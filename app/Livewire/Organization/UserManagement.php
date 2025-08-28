<?php

namespace App\Livewire\Organization;

use App\Contracts\OrganizationServiceInterface;
use App\Helpers\OrganizationContext;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public $organization;

    public $showAddUserForm = false;

    public $showEditUserForm = false;

    public $selectedUser = null;

    // Form properties
    public $userEmail = '';

    public $userRole = 'member';

    public $userPermissions = [];

    public $searchTerm = '';

    // Available roles and permissions
    public $availableRoles = [
        'owner' => 'Owner',
        'admin' => 'Administrator',
        'member' => 'Member',
        'viewer' => 'Viewer',
    ];

    public $availablePermissions = [
        'view_servers' => 'View Servers',
        'manage_servers' => 'Manage Servers',
        'view_applications' => 'View Applications',
        'manage_applications' => 'Manage Applications',
        'deploy_applications' => 'Deploy Applications',
        'view_billing' => 'View Billing',
        'manage_billing' => 'Manage Billing',
        'manage_users' => 'Manage Users',
        'manage_organization' => 'Manage Organization',
    ];

    protected $rules = [
        'userEmail' => 'required|email|exists:users,email',
        'userRole' => 'required|in:owner,admin,member,viewer',
        'userPermissions' => 'array',
    ];

    public function mount(Organization $organization)
    {
        $this->organization = $organization;

        // Check permissions
        if (! OrganizationContext::can('manage_users', $organization)) {
            abort(403, 'You do not have permission to manage users for this organization.');
        }
    }

    public function render()
    {
        $users = $this->organization->users()
            ->when($this->searchTerm, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->searchTerm.'%')
                        ->orWhere('email', 'like', '%'.$this->searchTerm.'%');
                });
            })
            ->paginate(10);

        $availableUsers = $this->getAvailableUsers();

        return view('livewire.organization.user-management', [
            'users' => $users,
            'availableUsers' => $availableUsers,
        ]);
    }

    public function addUser()
    {
        $this->validate();

        try {
            $user = User::where('email', $this->userEmail)->firstOrFail();

            // Check if user is already in organization
            if ($this->organization->users()->where('user_id', $user->id)->exists()) {
                session()->flash('error', 'User is already a member of this organization.');

                return;
            }

            $organizationService = app(OrganizationServiceInterface::class);

            $organizationService->attachUserToOrganization(
                $this->organization,
                $user,
                $this->userRole,
                $this->userPermissions
            );

            $this->resetForm();
            $this->showAddUserForm = false;

            session()->flash('success', 'User added to organization successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add user: '.$e->getMessage());
        }
    }

    public function editUser(User $user)
    {
        $this->selectedUser = $user;
        $userOrg = $this->organization->users()->where('user_id', $user->id)->first();

        if (! $userOrg) {
            session()->flash('error', 'User not found in organization.');

            return;
        }

        $this->userRole = $userOrg->pivot->role;
        $this->userPermissions = $userOrg->pivot->permissions ?? [];
        $this->showEditUserForm = true;
    }

    public function updateUser()
    {
        $this->validate([
            'userRole' => 'required|in:owner,admin,member,viewer',
            'userPermissions' => 'array',
        ]);

        try {
            // Prevent removing the last owner
            if ($this->isLastOwner($this->selectedUser) && $this->userRole !== 'owner') {
                session()->flash('error', 'Cannot change role of the last owner.');

                return;
            }

            $organizationService = app(OrganizationServiceInterface::class);

            $organizationService->updateUserRole(
                $this->organization,
                $this->selectedUser,
                $this->userRole,
                $this->userPermissions
            );

            $this->resetForm();
            $this->showEditUserForm = false;

            session()->flash('success', 'User updated successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user: '.$e->getMessage());
        }
    }

    public function removeUser(User $user)
    {
        try {
            // Prevent removing the last owner
            if ($this->isLastOwner($user)) {
                session()->flash('error', 'Cannot remove the last owner from the organization.');

                return;
            }

            // Prevent users from removing themselves unless they're not the last owner
            if ($user->id === Auth::id() && $this->isLastOwner($user)) {
                session()->flash('error', 'You cannot remove yourself as the last owner.');

                return;
            }

            $organizationService = app(OrganizationServiceInterface::class);

            $organizationService->detachUserFromOrganization($this->organization, $user);

            session()->flash('success', 'User removed from organization successfully.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to remove user: '.$e->getMessage());
        }
    }

    public function getAvailableUsers()
    {
        if (! $this->userEmail || strlen($this->userEmail) < 3) {
            return collect();
        }

        return User::where('email', 'like', '%'.$this->userEmail.'%')
            ->whereNotIn('id', $this->organization->users()->pluck('user_id'))
            ->limit(10)
            ->get();
    }

    public function selectUser($userId)
    {
        $user = User::find($userId);
        if ($user) {
            $this->userEmail = $user->email;
        }
    }

    public function getUserRole(User $user)
    {
        $userOrg = $this->organization->users()->where('user_id', $user->id)->first();

        return $userOrg?->pivot->role ?? 'unknown';
    }

    public function getUserPermissions(User $user)
    {
        $userOrg = $this->organization->users()->where('user_id', $user->id)->first();

        return $userOrg?->pivot->permissions ?? [];
    }

    public function canEditUser(User $user)
    {
        // Owners can edit anyone except other owners (unless they're the only owner)
        // Admins can edit members and viewers
        // Members and viewers cannot edit anyone

        $currentUserRole = OrganizationContext::getUserRole();
        $targetUserRole = $this->getUserRole($user);

        if ($currentUserRole === 'owner') {
            return true;
        }

        if ($currentUserRole === 'admin') {
            return in_array($targetUserRole, ['member', 'viewer']);
        }

        return false;
    }

    public function canRemoveUser(User $user)
    {
        // Same logic as canEditUser, but also prevent removing the last owner
        return $this->canEditUser($user) && ! $this->isLastOwner($user);
    }

    protected function isLastOwner(User $user)
    {
        $owners = $this->organization->users()
            ->wherePivot('role', 'owner')
            ->wherePivot('is_active', true)
            ->get();

        return $owners->count() === 1 && $owners->first()->id === $user->id;
    }

    protected function resetForm()
    {
        $this->userEmail = '';
        $this->userRole = 'member';
        $this->userPermissions = [];
        $this->selectedUser = null;
    }

    public function openAddUserForm()
    {
        $this->showAddUserForm = true;
    }

    public function closeModals()
    {
        $this->showAddUserForm = false;
        $this->showEditUserForm = false;
        $this->resetForm();
    }

    public function getRoleColor($role)
    {
        return match ($role) {
            'owner' => 'bg-red-100 text-red-800',
            'admin' => 'bg-blue-100 text-blue-800',
            'member' => 'bg-green-100 text-green-800',
            'viewer' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
}
