<?php

namespace App\Helpers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OrganizationContext
{
    /**
     * Get the current organization for the authenticated user
     */
    public static function current(): ?Organization
    {
        $user = Auth::user();

        return $user?->currentOrganization;
    }

    /**
     * Get the current organization ID for the authenticated user
     */
    public static function currentId(): ?string
    {
        return static::current()?->id;
    }

    /**
     * Check if the current user can perform an action in their current organization
     */
    public static function can(string $action, $resource = null): bool
    {
        $user = Auth::user();
        $organization = static::current();

        if (! $user || ! $organization) {
            return false;
        }

        return app(\App\Contracts\OrganizationServiceInterface::class)
            ->canUserPerformAction($user, $organization, $action, $resource);
    }

    /**
     * Check if the current organization has a specific feature
     */
    public static function hasFeature(string $feature): bool
    {
        return static::current()?->hasFeature($feature) ?? false;
    }

    /**
     * Get usage metrics for the current organization
     */
    public static function getUsage(): array
    {
        $organization = static::current();

        if (! $organization) {
            return [];
        }

        return app(\App\Contracts\OrganizationServiceInterface::class)
            ->getOrganizationUsage($organization);
    }

    /**
     * Check if the current organization is within its limits
     */
    public static function isWithinLimits(): bool
    {
        return static::current()?->isWithinLimits() ?? false;
    }

    /**
     * Get the hierarchy type of the current organization
     */
    public static function getHierarchyType(): ?string
    {
        return static::current()?->hierarchy_type;
    }

    /**
     * Check if the current organization is of a specific hierarchy type
     */
    public static function isHierarchyType(string $type): bool
    {
        return static::getHierarchyType() === $type;
    }

    /**
     * Get all organizations accessible by the current user
     */
    public static function getUserOrganizations(): \Illuminate\Database\Eloquent\Collection
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        return app(\App\Contracts\OrganizationServiceInterface::class)
            ->getUserOrganizations($user);
    }

    /**
     * Switch to a different organization
     */
    public static function switchTo(Organization $organization): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        try {
            app(\App\Contracts\OrganizationServiceInterface::class)
                ->switchUserOrganization($user, $organization);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the organization hierarchy starting from the current organization
     */
    public static function getHierarchy(): array
    {
        $organization = static::current();

        if (! $organization) {
            return [];
        }

        return app(\App\Contracts\OrganizationServiceInterface::class)
            ->getOrganizationHierarchy($organization);
    }

    /**
     * Check if the current user is an owner of the current organization
     */
    public static function isOwner(): bool
    {
        $user = Auth::user();
        $organization = static::current();

        if (! $user || ! $organization) {
            return false;
        }

        $userOrg = $organization->users()->where('user_id', $user->id)->first();

        return $userOrg && $userOrg->pivot->role === 'owner';
    }

    /**
     * Check if the current user is an admin of the current organization
     */
    public static function isAdmin(): bool
    {
        $user = Auth::user();
        $organization = static::current();

        if (! $user || ! $organization) {
            return false;
        }

        $userOrg = $organization->users()->where('user_id', $user->id)->first();

        return $userOrg && in_array($userOrg->pivot->role, ['owner', 'admin']);
    }

    /**
     * Get the current user's role in the current organization
     */
    public static function getUserRole(): ?string
    {
        $user = Auth::user();
        $organization = static::current();

        if (! $user || ! $organization) {
            return null;
        }

        $userOrg = $organization->users()->where('user_id', $user->id)->first();

        return $userOrg?->pivot->role;
    }

    /**
     * Get the current user's permissions in the current organization
     */
    public static function getUserPermissions(): array
    {
        $user = Auth::user();
        $organization = static::current();

        if (! $user || ! $organization) {
            return [];
        }

        $userOrg = $organization->users()->where('user_id', $user->id)->first();

        return $userOrg?->pivot->permissions ?? [];
    }
}
