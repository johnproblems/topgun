<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseLicense extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'license_key',
        'license_type',
        'license_tier',
        'features',
        'limits',
        'issued_at',
        'expires_at',
        'authorized_domains',
        'status',
    ];

    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'authorized_domains' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Feature Checking Methods
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function hasAnyFeature(array $features): bool
    {
        return ! empty(array_intersect($features, $this->features ?? []));
    }

    public function hasAllFeatures(array $features): bool
    {
        return empty(array_diff($features, $this->features ?? []));
    }

    // Validation Methods
    public function isValid(): bool
    {
        return $this->status === 'active' &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function isDomainAuthorized(string $domain): bool
    {
        if (empty($this->authorized_domains)) {
            return true; // No domain restrictions
        }

        // Check exact match
        if (in_array($domain, $this->authorized_domains)) {
            return true;
        }

        // Check wildcard domains
        foreach ($this->authorized_domains as $authorizedDomain) {
            if (str_starts_with($authorizedDomain, '*.')) {
                $pattern = str_replace('*.', '', $authorizedDomain);
                if (str_ends_with($domain, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    // Limit Checking Methods
    public function isWithinLimits(): bool
    {
        if (! $this->organization) {
            return false;
        }

        $usage = $this->organization->getUsageMetrics();
        $limits = $this->limits ?? [];

        foreach ($limits as $limitType => $limitValue) {
            $currentUsage = $usage[$limitType] ?? 0;
            if ($currentUsage > $limitValue) {
                return false;
            }
        }

        return true;
    }

    public function getLimitViolations(): array
    {
        if (! $this->organization) {
            return [];
        }

        $usage = $this->organization->getUsageMetrics();
        $limits = $this->limits ?? [];
        $violations = [];

        foreach ($limits as $limitType => $limitValue) {
            $currentUsage = $usage[$limitType] ?? 0;
            if ($currentUsage > $limitValue) {
                $violations[] = [
                    'type' => $limitType,
                    'limit' => $limitValue,
                    'current' => $currentUsage,
                    'message' => ucfirst($limitType)." count ({$currentUsage}) exceeds limit ({$limitValue})",
                ];
            }
        }

        return $violations;
    }

    public function getLimit(string $limitType): ?int
    {
        return $this->limits[$limitType] ?? null;
    }

    public function getRemainingLimit(string $limitType): ?int
    {
        $limit = $this->getLimit($limitType);
        if ($limit === null) {
            return null; // No limit set
        }

        $usage = $this->organization?->getUsageMetrics()[$limitType] ?? 0;

        return max(0, $limit - $usage);
    }

    // License Type Methods
    public function isPerpetual(): bool
    {
        return $this->license_type === 'perpetual';
    }

    public function isSubscription(): bool
    {
        return $this->license_type === 'subscription';
    }

    public function isTrial(): bool
    {
        return $this->license_type === 'trial';
    }

    // License Tier Methods
    public function isBasic(): bool
    {
        return $this->license_tier === 'basic';
    }

    public function isProfessional(): bool
    {
        return $this->license_tier === 'professional';
    }

    public function isEnterprise(): bool
    {
        return $this->license_tier === 'enterprise';
    }

    // Status Management
    public function activate(): bool
    {
        $this->status = 'active';

        return $this->save();
    }

    public function suspend(): bool
    {
        $this->status = 'suspended';

        return $this->save();
    }

    public function revoke(): bool
    {
        $this->status = 'revoked';

        return $this->save();
    }

    public function markAsExpired(): bool
    {
        $this->status = 'expired';

        return $this->save();
    }

    // Validation Tracking
    public function updateLastValidated(): bool
    {
        $this->last_validated_at = now();

        return $this->save();
    }

    public function getDaysUntilExpiration(): ?int
    {
        if ($this->expires_at === null) {
            return null; // Never expires
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function isExpiringWithin(int $days): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isBefore(now()->addDays($days));
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }
}
