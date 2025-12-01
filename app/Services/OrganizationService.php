<?php

namespace App\Services;

use App\Contracts\OrganizationServiceInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrganizationService implements OrganizationServiceInterface
{
    /**
     * Create a new organization with proper hierarchy validation
     */
    public function createOrganization(array $data, ?Organization $parent = null): Organization
    {
        $this->validateOrganizationData($data);

        if ($parent) {
            $this->validateHierarchyCreation($parent, $data['hierarchy_type']);
        }

        return DB::transaction(function () use ($data, $parent) {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'hierarchy_type' => $data['hierarchy_type'],
                'hierarchy_level' => $parent ? $parent->hierarchy_level + 1 : 0,
                'parent_organization_id' => $parent?->id,
                'branding_config' => $data['branding_config'] ?? [],
                'feature_flags' => $data['feature_flags'] ?? [],
                'is_active' => $data['is_active'] ?? true,
            ]);

            // If creating with an owner, attach them
            if (isset($data['owner_id'])) {
                $this->attachUserToOrganization(
                    $organization,
                    User::findOrFail($data['owner_id']),
                    'owner'
                );
            }

            return $organization;
        });
    }

    /**
     * Update organization with validation
     */
    public function updateOrganization(Organization $organization, array $data): Organization
    {
        $this->validateOrganizationData($data, $organization);

        return DB::transaction(function () use ($organization, $data) {
            // Don't allow changing hierarchy type if it would break relationships
            if (isset($data['hierarchy_type']) && $data['hierarchy_type'] !== $organization->hierarchy_type) {
                $this->validateHierarchyTypeChange($organization, $data['hierarchy_type']);
            }

            $organization->update($data);

            // Clear cached permissions for this organization
            $this->clearOrganizationCache($organization);

            return $organization->fresh();
        });
    }

    /**
     * Attach a user to an organization with a specific role
     */
    public function attachUserToOrganization(Organization $organization, User $user, string $role, array $permissions = []): void
    {
        $this->validateRole($role);
        $this->validateUserCanBeAttached($organization, $user, $role);

        $organization->users()->attach($user->id, [
            'role' => $role,
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        // Clear user's cached permissions
        $this->clearUserCache($user);
    }

    /**
     * Update user's role and permissions in an organization
     */
    public function updateUserRole(Organization $organization, User $user, string $role, array $permissions = []): void
    {
        $this->validateRole($role);

        $organization->users()->updateExistingPivot($user->id, [
            'role' => $role,
            'permissions' => $permissions,
        ]);

        $this->clearUserCache($user);
    }

    /**
     * Remove user from organization
     */
    public function detachUserFromOrganization(Organization $organization, User $user): void
    {
        // Prevent removing the last owner
        if ($this->isLastOwner($organization, $user)) {
            throw new InvalidArgumentException('Cannot remove the last owner from an organization');
        }

        $organization->users()->detach($user->id);
        $this->clearUserCache($user);
    }

    /**
     * Switch user's current organization context
     */
    public function switchUserOrganization(User $user, Organization $organization): void
    {
        // Verify user has access to this organization
        if (! $this->userHasAccessToOrganization($user, $organization)) {
            throw new InvalidArgumentException('User does not have access to this organization');
        }

        $user->update(['current_organization_id' => $organization->id]);
        $this->clearUserCache($user);
    }

    /**
     * Get organizations accessible by a user
     */
    public function getUserOrganizations(User $user): Collection
    {
        return Cache::remember(
            "user_organizations_{$user->id}",
            now()->addMinutes(30),
            fn () => $user->organizations()->wherePivot('is_active', true)->get()
        );
    }

    /**
     * Check if user can perform an action on a resource within an organization
     */
    public function canUserPerformAction(User $user, Organization $organization, string $action, $resource = null): bool
    {
        $cacheKey = "user_permissions_{$user->id}_{$organization->id}_{$action}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $organization, $action, $resource) {
            // Check if user is in organization
            $userOrg = $organization->users()->where('user_id', $user->id)->first();
            if (! $userOrg || ! $userOrg->pivot->is_active) {
                return false;
            }

            // Check license restrictions
            if (! $this->isActionAllowedByLicense($organization, $action)) {
                return false;
            }

            // Check role-based permissions
            $permissions = $userOrg->pivot->permissions ?? [];
            if (is_string($permissions)) {
                $permissions = json_decode($permissions, true) ?? [];
            }

            return $this->checkRolePermission(
                $userOrg->pivot->role,
                $permissions,
                $action,
                $resource
            );
        });
    }

    /**
     * Get organization hierarchy tree
     */
    public function getOrganizationHierarchy(Organization $rootOrganization): array
    {
        return Cache::remember(
            "org_hierarchy_{$rootOrganization->id}",
            now()->addHour(),
            fn () => $this->buildHierarchyTree($rootOrganization)
        );
    }

    /**
     * Move organization to a new parent (with validation)
     */
    public function moveOrganization(Organization $organization, ?Organization $newParent): Organization
    {
        if ($newParent) {
            // Prevent circular dependencies
            if ($this->wouldCreateCircularDependency($organization, $newParent)) {
                throw new InvalidArgumentException('Moving organization would create circular dependency');
            }

            // Validate hierarchy rules
            $this->validateHierarchyMove($organization, $newParent);
        }

        return DB::transaction(function () use ($organization, $newParent) {
            $oldLevel = $organization->hierarchy_level;
            $newLevel = $newParent ? $newParent->hierarchy_level + 1 : 0;
            $levelDifference = $newLevel - $oldLevel;

            // Update the organization
            $organization->update([
                'parent_organization_id' => $newParent?->id,
                'hierarchy_level' => $newLevel,
            ]);

            // Update all descendants' hierarchy levels
            if ($levelDifference !== 0) {
                $this->updateDescendantLevels($organization, $levelDifference);
            }

            // Clear relevant caches
            $this->clearOrganizationCache($organization);
            if ($newParent) {
                $this->clearOrganizationCache($newParent);
            }

            return $organization->fresh();
        });
    }

    /**
     * Delete organization with proper cleanup
     */
    public function deleteOrganization(Organization $organization, bool $force = false): bool
    {
        return DB::transaction(function () use ($organization, $force) {
            // Check if organization has children
            if ($organization->children()->exists() && ! $force) {
                throw new InvalidArgumentException('Cannot delete organization with child organizations');
            }

            // Check if organization has active resources
            if ($this->hasActiveResources($organization) && ! $force) {
                throw new InvalidArgumentException('Cannot delete organization with active resources');
            }

            // If force delete, handle children
            if ($force && $organization->children()->exists()) {
                // Move children to parent or make them orphans
                $parent = $organization->parent;
                foreach ($organization->children as $child) {
                    $this->moveOrganization($child, $parent);
                }
            }

            // Clear caches
            $this->clearOrganizationCache($organization);

            // Soft delete the organization
            return $organization->delete();
        });
    }

    /**
     * Get organization usage statistics
     */
    public function getOrganizationUsage(Organization $organization): array
    {
        return Cache::remember(
            "org_usage_{$organization->id}",
            now()->addMinutes(5),
            fn () => [
                'users' => $organization->users()->wherePivot('is_active', true)->count(),
                'servers' => $organization->servers()->count(),
                'applications' => $organization->applications()->count(),
                'children' => $organization->children()->count(),
                'storage_used' => $this->calculateStorageUsage($organization),
                'monthly_costs' => $this->calculateMonthlyCosts($organization),
            ]
        );
    }

    /**
     * Validate organization data
     */
    protected function validateOrganizationData(array $data, ?Organization $existing = null): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'hierarchy_type' => 'required|in:top_branch,master_branch,sub_user,end_user',
        ];

        // Check slug uniqueness
        if (isset($data['slug'])) {
            $slugQuery = Organization::where('slug', $data['slug']);
            if ($existing) {
                $slugQuery->where('id', '!=', $existing->id);
            }
            if ($slugQuery->exists()) {
                throw new InvalidArgumentException('Organization slug must be unique');
            }
        }

        // Validate hierarchy type
        $validTypes = ['top_branch', 'master_branch', 'sub_user', 'end_user'];
        if (isset($data['hierarchy_type']) && ! in_array($data['hierarchy_type'], $validTypes)) {
            throw new InvalidArgumentException('Invalid hierarchy type');
        }
    }

    /**
     * Validate hierarchy creation rules
     */
    protected function validateHierarchyCreation(Organization $parent, string $childType): void
    {
        $allowedChildren = [
            'top_branch' => ['master_branch'],
            'master_branch' => ['sub_user'],
            'sub_user' => ['end_user'],
            'end_user' => [], // End users cannot have children
        ];

        $parentType = $parent->hierarchy_type ?? '';

        if (! isset($allowedChildren[$parentType]) || ! in_array($childType, $allowedChildren[$parentType])) {
            throw new InvalidArgumentException("A {$parentType} cannot have a {$childType} as a child");
        }
    }

    /**
     * Validate role
     */
    protected function validateRole(string $role): void
    {
        $validRoles = ['owner', 'admin', 'member', 'viewer'];
        if (! in_array($role, $validRoles)) {
            throw new InvalidArgumentException('Invalid role');
        }
    }

    /**
     * Check if user can be attached to organization
     */
    protected function validateUserCanBeAttached(Organization $organization, User $user, string $role): void
    {
        // Check if user is already in organization
        if ($organization->users()->where('user_id', $user->id)->exists()) {
            throw new InvalidArgumentException('User is already in this organization');
        }

        // Check license limits
        $license = $organization->activeLicense;
        if ($license && isset($license->limits['max_users'])) {
            $currentUsers = $organization->users()->wherePivot('is_active', true)->count();
            if ($currentUsers >= $license->limits['max_users']) {
                throw new InvalidArgumentException('Organization has reached maximum user limit');
            }
        }
    }

    /**
     * Check if user is the last owner
     */
    protected function isLastOwner(Organization $organization, User $user): bool
    {
        $owners = $organization->users()->wherePivot('role', 'owner')->wherePivot('is_active', true)->get();

        return $owners->count() === 1 && $owners->first()->id === $user->id;
    }

    /**
     * Check if user has access to organization
     */
    protected function userHasAccessToOrganization(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->where('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Check if action is allowed by license
     */
    protected function isActionAllowedByLicense(Organization $organization, string $action): bool
    {
        $license = $organization->activeLicense;
        if (! $license || ! $license->isValid()) {
            // Allow basic actions without license
            $basicActions = ['view_servers', 'view_applications'];

            return in_array($action, $basicActions);
        }

        // Map actions to license features
        $actionFeatureMap = [
            'provision_infrastructure' => 'infrastructure_provisioning',
            'manage_domains' => 'domain_management',
            'process_payments' => 'payment_processing',
            'manage_white_label' => 'white_label_branding',
        ];

        if (isset($actionFeatureMap[$action])) {
            return $license->hasFeature($actionFeatureMap[$action]);
        }

        return true; // Allow actions not mapped to specific features
    }

    /**
     * Check role-based permissions
     */
    protected function checkRolePermission(string $role, array $permissions, string $action, $resource = null): bool
    {
        // Owner can do everything
        if ($role === 'owner') {
            return true;
        }

        // Admin can do most things except organization management
        if ($role === 'admin') {
            $restrictedActions = ['delete_organization', 'manage_billing', 'manage_licenses'];

            return ! in_array($action, $restrictedActions);
        }

        // Member has limited permissions
        if ($role === 'member') {
            $allowedActions = ['view_servers', 'view_applications', 'deploy_applications', 'manage_applications'];

            return in_array($action, $allowedActions);
        }

        // Viewer can only view
        if ($role === 'viewer') {
            $allowedActions = ['view_servers', 'view_applications'];

            return in_array($action, $allowedActions);
        }

        // Check custom permissions
        return in_array($action, $permissions);
    }

    /**
     * Build hierarchy tree recursively
     */
    protected function buildHierarchyTree(Organization $organization): array
    {
        $children = $organization->children()->with('users')->get();

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'hierarchy_type' => $organization->hierarchy_type,
            'hierarchy_level' => $organization->hierarchy_level,
            'user_count' => $organization->users()->wherePivot('is_active', true)->count(),
            'is_active' => $organization->is_active,
            'children' => $children->map(fn ($child) => $this->buildHierarchyTree($child))->toArray(),
        ];
    }

    /**
     * Check if moving would create circular dependency
     */
    protected function wouldCreateCircularDependency(Organization $organization, Organization $newParent): bool
    {
        $current = $newParent;
        while ($current) {
            if ($current->id === $organization->id) {
                return true;
            }
            $current = $current->parent ?? null;
        }

        return false;
    }

    /**
     * Validate hierarchy move
     */
    protected function validateHierarchyMove(Organization $organization, Organization $newParent): void
    {
        // Check if the move respects hierarchy rules
        $this->validateHierarchyCreation($newParent, $organization->hierarchy_type);

        // Check if new parent can accept more children (license limits)
        $license = $newParent->activeLicense;
        if ($license && isset($license->limits['max_child_organizations'])) {
            $currentChildren = $newParent->children()->count();
            if ($currentChildren >= $license->limits['max_child_organizations']) {
                throw new InvalidArgumentException('Parent organization has reached maximum child limit');
            }
        }
    }

    /**
     * Update descendant hierarchy levels
     */
    protected function updateDescendantLevels(Organization $organization, int $levelDifference): void
    {
        $descendants = $organization->getAllDescendants();
        foreach ($descendants as $descendant) {
            $descendant->update([
                'hierarchy_level' => $descendant->hierarchy_level + $levelDifference,
            ]);
        }
    }

    /**
     * Check if organization has active resources
     */
    protected function hasActiveResources(Organization $organization): bool
    {
        return $organization->servers()->exists() ||
               $organization->applications()->exists() ||
               $organization->terraformDeployments()->where('status', '!=', 'destroyed')->exists();
    }

    /**
     * Calculate storage usage for organization
     */
    protected function calculateStorageUsage(Organization $organization): int
    {
        // This would integrate with actual storage monitoring
        // For now, return a placeholder
        return 0;
    }

    /**
     * Calculate monthly costs for organization
     */
    protected function calculateMonthlyCosts(Organization $organization): float
    {
        // This would integrate with actual cost tracking
        // For now, return a placeholder
        return 0.0;
    }

    /**
     * Validate hierarchy type change
     */
    protected function validateHierarchyTypeChange(Organization $organization, string $newType): void
    {
        // Check if change would break parent-child relationships
        if ($organization->parent) {
            $this->validateHierarchyCreation($organization->parent, $newType);
        }

        // Check if change would break relationships with children
        foreach ($organization->children as $child) {
            $this->validateHierarchyCreation($organization, $child->hierarchy_type);
        }
    }

    /**
     * Clear organization-related caches
     */
    protected function clearOrganizationCache(Organization $organization): void
    {
        Cache::forget("org_hierarchy_{$organization->id}");
        Cache::forget("org_usage_{$organization->id}");

        // Clear user caches for all users in this organization
        $organization->users->each(fn ($user) => $this->clearUserCache($user));
    }

    /**
     * Clear user-related caches
     */
    protected function clearUserCache(User $user): void
    {
        Cache::forget("user_organizations_{$user->id}");

        // Clear permission caches for all organizations this user belongs to
        $user->organizations->each(function ($org) use ($user) {
            $pattern = "user_permissions_{$user->id}_{$org->id}_*";
            // In a real implementation, you'd want a more sophisticated cache clearing mechanism
            // For now, we'll clear specific known permission keys
            $actions = ['view_servers', 'manage_servers', 'deploy_applications', 'manage_billing'];
            foreach ($actions as $action) {
                Cache::forget("user_permissions_{$user->id}_{$org->id}_{$action}");
            }
        });
    }
}
