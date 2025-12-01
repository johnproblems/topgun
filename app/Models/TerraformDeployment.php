<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerraformDeployment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'server_id',
        'provider_credential_id',
        'terraform_state',
        'deployment_config',
        'status',
        'error_message',
    ];

    protected $casts = [
        'terraform_state' => 'array',
        'deployment_config' => 'array',
    ];

    // Deployment statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DESTROYING = 'destroying';

    public const STATUS_DESTROYED = 'destroyed';

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function providerCredential()
    {
        return $this->belongsTo(CloudProviderCredential::class, 'provider_credential_id');
    }

    // Status Methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPlanning(): bool
    {
        return $this->status === self::STATUS_PLANNING;
    }

    public function isProvisioning(): bool
    {
        return $this->status === self::STATUS_PROVISIONING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDestroying(): bool
    {
        return $this->status === self::STATUS_DESTROYING;
    }

    public function isDestroyed(): bool
    {
        return $this->status === self::STATUS_DESTROYED;
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PLANNING,
            self::STATUS_PROVISIONING,
            self::STATUS_DESTROYING,
        ]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_DESTROYED,
        ]);
    }

    // Status Update Methods
    public function markAsPending(): void
    {
        $this->update(['status' => self::STATUS_PENDING, 'error_message' => null]);
    }

    public function markAsPlanning(): void
    {
        $this->update(['status' => self::STATUS_PLANNING, 'error_message' => null]);
    }

    public function markAsProvisioning(): void
    {
        $this->update(['status' => self::STATUS_PROVISIONING, 'error_message' => null]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED, 'error_message' => null]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update(['status' => self::STATUS_FAILED, 'error_message' => $errorMessage]);
    }

    public function markAsDestroying(): void
    {
        $this->update(['status' => self::STATUS_DESTROYING, 'error_message' => null]);
    }

    public function markAsDestroyed(): void
    {
        $this->update(['status' => self::STATUS_DESTROYED, 'error_message' => null]);
    }

    // Configuration Methods
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->deployment_config, $key, $default);
    }

    public function setConfigValue(string $key, $value): void
    {
        $config = $this->deployment_config ?? [];
        data_set($config, $key, $value);
        $this->deployment_config = $config;
    }

    public function getInstanceType(): ?string
    {
        return $this->getConfigValue('instance_type');
    }

    public function getRegion(): ?string
    {
        return $this->getConfigValue('region') ?? $this->providerCredential?->provider_region;
    }

    public function getServerName(): ?string
    {
        return $this->getConfigValue('server_name') ?? "server-{$this->id}";
    }

    public function getDiskSize(): ?int
    {
        return $this->getConfigValue('disk_size', 20);
    }

    public function getNetworkConfig(): array
    {
        return $this->getConfigValue('network', []);
    }

    public function getSecurityGroupConfig(): array
    {
        return $this->getConfigValue('security_groups', []);
    }

    // Terraform State Methods
    public function getStateValue(string $key, $default = null)
    {
        return data_get($this->terraform_state, $key, $default);
    }

    public function setStateValue(string $key, $value): void
    {
        $state = $this->terraform_state ?? [];
        data_set($state, $key, $value);
        $this->terraform_state = $state;
    }

    public function getOutputs(): array
    {
        return $this->getStateValue('outputs', []);
    }

    public function getOutput(string $key, $default = null)
    {
        return data_get($this->getOutputs(), $key, $default);
    }

    public function getPublicIp(): ?string
    {
        return $this->getOutput('public_ip');
    }

    public function getPrivateIp(): ?string
    {
        return $this->getOutput('private_ip');
    }

    public function getInstanceId(): ?string
    {
        return $this->getOutput('instance_id');
    }

    public function getSshPrivateKey(): ?string
    {
        return $this->getOutput('ssh_private_key');
    }

    public function getSshPublicKey(): ?string
    {
        return $this->getOutput('ssh_public_key');
    }

    // Resource Management Methods
    public function getResourceIds(): array
    {
        return $this->getStateValue('resource_ids', []);
    }

    public function addResourceId(string $type, string $id): void
    {
        $resourceIds = $this->getResourceIds();
        $resourceIds[$type] = $id;
        $this->setStateValue('resource_ids', $resourceIds);
    }

    public function getResourceId(string $type): ?string
    {
        return $this->getResourceIds()[$type] ?? null;
    }

    // Provider-specific Methods
    public function getProviderName(): string
    {
        return $this->providerCredential->provider_name;
    }

    public function isAwsDeployment(): bool
    {
        return $this->getProviderName() === 'aws';
    }

    public function isGcpDeployment(): bool
    {
        return $this->getProviderName() === 'gcp';
    }

    public function isAzureDeployment(): bool
    {
        return $this->getProviderName() === 'azure';
    }

    public function isDigitalOceanDeployment(): bool
    {
        return $this->getProviderName() === 'digitalocean';
    }

    public function isHetznerDeployment(): bool
    {
        return $this->getProviderName() === 'hetzner';
    }

    // Validation Methods
    public function canBeDestroyed(): bool
    {
        return $this->isCompleted() && ! $this->isDestroyed();
    }

    public function canBeRetried(): bool
    {
        return $this->isFailed();
    }

    public function hasServer(): bool
    {
        return $this->server_id !== null;
    }

    public function hasValidCredentials(): bool
    {
        return $this->providerCredential && $this->providerCredential->isValidated();
    }

    // Cost Estimation Methods (placeholder for future implementation)
    public function getEstimatedMonthlyCost(): ?float
    {
        // This would integrate with cloud provider pricing APIs
        // For now, return null as placeholder
        return null;
    }

    public function getEstimatedHourlyCost(): ?float
    {
        $monthlyCost = $this->getEstimatedMonthlyCost();

        return $monthlyCost ? $monthlyCost / (24 * 30) : null;
    }

    // Scopes
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PLANNING,
            self::STATUS_PROVISIONING,
            self::STATUS_DESTROYING,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->whereHas('providerCredential', function ($q) use ($provider) {
            $q->where('provider_name', $provider);
        });
    }

    public function scopeForOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // Helper Methods
    public function getDurationInMinutes(): ?int
    {
        if (! $this->isFinished()) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->updated_at);
    }

    public function getFormattedDuration(): ?string
    {
        $minutes = $this->getDurationInMinutes();
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }

    public function toArray()
    {
        $array = parent::toArray();

        // Add computed properties
        $array['provider_name'] = $this->getProviderName();
        $array['duration_minutes'] = $this->getDurationInMinutes();
        $array['formatted_duration'] = $this->getFormattedDuration();
        $array['can_be_destroyed'] = $this->canBeDestroyed();
        $array['can_be_retried'] = $this->canBeRetried();

        return $array;
    }
}
