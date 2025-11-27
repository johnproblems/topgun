<?php

namespace App\Contracts;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface OrganizationServiceInterface
{
    /**
     * Create a new organization with proper hierarchy validation
     */
    public function createOrganization(array $data, ?Organization $parent = null): Organization;

    /**
     * Update organization with validation
     */
    public function updateOrganization(Organization $organization, array $data): Organization;

    /**
     * Attach a user to an organization with a specific role
     */
    public function attachUserToOrganization(Organization $organization, User $user, string $role, array $permissions = []): void;

    /**
     * Update user's role and permissions in an organization
     */
    public function updateUserRole(Organization $organization, User $user, string $role, array $permissions = []): void;

    /**
     * Remove user from organization
     */
    public function detachUserFromOrganization(Organization $organization, User $user): void;

    /**
     * Switch user's current organization context
     */
    public function switchUserOrganization(User $user, Organization $organization): void;

    /**
     * Get organizations accessible by a user
     */
    public function getUserOrganizations(User $user): Collection;

    /**
     * Check if user can perform an action on a resource within an organization
     */
    public function canUserPerformAction(User $user, Organization $organization, string $action, $resource = null): bool;

    /**
     * Get organization hierarchy tree
     */
    public function getOrganizationHierarchy(Organization $rootOrganization): array;

    /**
     * Move organization to a new parent (with validation)
     */
    public function moveOrganization(Organization $organization, ?Organization $newParent): Organization;

    /**
     * Delete organization with proper cleanup
     */
    public function deleteOrganization(Organization $organization, bool $force = false): bool;

    /**
     * Get organization usage statistics
     */
    public function getOrganizationUsage(Organization $organization): array;
}
