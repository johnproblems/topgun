<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'hierarchy_type',
        'hierarchy_level',
        'parent_organization_id',
        'branding_config',
        'feature_flags',
        'is_active',
    ];

    protected $casts = [
        'branding_config' => 'array',
        'feature_flags' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_organization_id');
    }

    public function children()
    {
        return $this->hasMany(Organization::class, 'parent_organization_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->using(OrganizationUser::class)
            ->withPivot('role', 'permissions', 'is_active')
            ->withTimestamps();
    }

    public function activeLicense()
    {
        return $this->hasOne(EnterpriseLicense::class)->where('status', 'active');
    }

    public function licenses()
    {
        return $this->hasMany(EnterpriseLicense::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function whiteLabelConfig()
    {
        return $this->hasOne(WhiteLabelConfig::class);
    }

    public function cloudProviderCredentials()
    {
        return $this->hasMany(CloudProviderCredential::class);
    }

    public function terraformDeployments()
    {
        return $this->hasMany(TerraformDeployment::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    // Business Logic Methods
    public function canUserPerformAction(User $user, string $action, $resource = null): bool
    {
        $userOrg = $this->users()->where('user_id', $user->id)->first();
        if (! $userOrg) {
            return false;
        }

        $role = $userOrg->pivot->role;
        $permissions = $userOrg->pivot->permissions ?? [];

        return $this->checkPermission($role, $permissions, $action, $resource);
    }

    public function hasFeature(string $feature): bool
    {
        return $this->activeLicense?->hasFeature($feature) ?? false;
    }

    public function getUsageMetrics(): array
    {
        try {
            return [
                'users' => $this->users()->count(),
                'servers' => $this->servers()->count(),
                'applications' => $this->applications()->count(),
                'domains' => $this->domains()->count(),
                'cloud_providers' => $this->cloudProviderCredentials()->count(),
            ];
        } catch (\Exception $e) {
            // Handle missing columns gracefully for development
            return [
                'users' => $this->users()->count(),
                'servers' => 0, // Fallback if servers relationship doesn't exist
                'applications' => 0, // Fallback if applications relationship doesn't exist
                'domains' => 0, // Fallback if domains relationship doesn't exist
                'cloud_providers' => 0, // Fallback if cloud_providers relationship doesn't exist
            ];
        }
    }

    public function isWithinLimits(): bool
    {
        $license = $this->activeLicense;
        if (! $license) {
            return false;
        }

        $limits = $license->limits ?? [];
        $usage = $this->getUsageMetrics();

        foreach ($limits as $limitType => $limitValue) {
            $currentUsage = $usage[$limitType] ?? 0;
            if ($currentUsage > $limitValue) {
                return false;
            }
        }

        return true;
    }

    public function getTeamId(): ?int
    {
        // Map organization to existing team system for backward compatibility
        // This is a temporary bridge until full migration to organizations
        $owner = $this->users()->wherePivot('role', 'owner')->first();

        return $owner?->teams()?->first()?->id;
    }

    protected function checkPermission(string $role, array $permissions, string $action, $resource = null): bool
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
            $allowedActions = ['view_servers', 'view_applications', 'deploy_applications'];

            return in_array($action, $allowedActions);
        }

        // Check custom permissions
        return in_array($action, $permissions);
    }

    // Hierarchy Methods
    public function isTopBranch(): bool
    {
        return $this->hierarchy_type === 'top_branch';
    }

    public function isMasterBranch(): bool
    {
        return $this->hierarchy_type === 'master_branch';
    }

    public function isSubUser(): bool
    {
        return $this->hierarchy_type === 'sub_user';
    }

    public function isEndUser(): bool
    {
        return $this->hierarchy_type === 'end_user';
    }

    public function getAllDescendants()
    {
        return $this->children()->with('children')->get()->flatMap(function ($child) {
            return collect([$child])->merge($child->getAllDescendants());
        });
    }

    public function getAncestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }
}
