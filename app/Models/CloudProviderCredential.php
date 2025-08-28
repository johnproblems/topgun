<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloudProviderCredential extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'provider_name',
        'provider_region',
        'credentials',
        'is_active',
        'last_validated_at',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_validated_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
    ];

    // Supported cloud providers
    public const SUPPORTED_PROVIDERS = [
        'aws' => 'Amazon Web Services',
        'gcp' => 'Google Cloud Platform',
        'azure' => 'Microsoft Azure',
        'digitalocean' => 'DigitalOcean',
        'hetzner' => 'Hetzner Cloud',
        'linode' => 'Linode',
        'vultr' => 'Vultr',
    ];

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function terraformDeployments()
    {
        return $this->hasMany(TerraformDeployment::class, 'provider_credential_id');
    }

    public function servers()
    {
        return $this->hasMany(Server::class, 'provider_credential_id');
    }

    // Provider Methods
    public function getProviderDisplayName(): string
    {
        return self::SUPPORTED_PROVIDERS[$this->provider_name] ?? $this->provider_name;
    }

    public function isProviderSupported(): bool
    {
        return array_key_exists($this->provider_name, self::SUPPORTED_PROVIDERS);
    }

    public static function getSupportedProviders(): array
    {
        return self::SUPPORTED_PROVIDERS;
    }

    // Credential Management Methods
    public function setCredentials(array $credentials): void
    {
        // Validate credentials based on provider
        $this->validateCredentialsForProvider($credentials);
        $this->credentials = $credentials;
    }

    public function getCredential(string $key): ?string
    {
        return $this->credentials[$key] ?? null;
    }

    public function hasCredential(string $key): bool
    {
        return isset($this->credentials[$key]) && ! empty($this->credentials[$key]);
    }

    public function getRequiredCredentialKeys(): array
    {
        return match ($this->provider_name) {
            'aws' => ['access_key_id', 'secret_access_key'],
            'gcp' => ['service_account_json'],
            'azure' => ['subscription_id', 'client_id', 'client_secret', 'tenant_id'],
            'digitalocean' => ['api_token'],
            'hetzner' => ['api_token'],
            'linode' => ['api_token'],
            'vultr' => ['api_key'],
            default => [],
        };
    }

    public function getOptionalCredentialKeys(): array
    {
        return match ($this->provider_name) {
            'aws' => ['session_token', 'region'],
            'gcp' => ['project_id', 'region'],
            'azure' => ['resource_group', 'location'],
            'digitalocean' => ['region'],
            'hetzner' => ['region'],
            'linode' => ['region'],
            'vultr' => ['region'],
            default => [],
        };
    }

    public function validateCredentialsForProvider(array $credentials): void
    {
        $requiredKeys = $this->getRequiredCredentialKeys();

        foreach ($requiredKeys as $key) {
            if (! isset($credentials[$key]) || empty($credentials[$key])) {
                throw new \InvalidArgumentException("Missing required credential: {$key}");
            }
        }

        // Provider-specific validation
        match ($this->provider_name) {
            'aws' => $this->validateAwsCredentials($credentials),
            'gcp' => $this->validateGcpCredentials($credentials),
            'azure' => $this->validateAzureCredentials($credentials),
            'digitalocean' => $this->validateDigitalOceanCredentials($credentials),
            'hetzner' => $this->validateHetznerCredentials($credentials),
            'linode' => $this->validateLinodeCredentials($credentials),
            'vultr' => $this->validateVultrCredentials($credentials),
            default => null,
        };
    }

    // Provider-specific validation methods
    private function validateAwsCredentials(array $credentials): void
    {
        if (strlen($credentials['access_key_id']) !== 20) {
            throw new \InvalidArgumentException('Invalid AWS Access Key ID format');
        }

        if (strlen($credentials['secret_access_key']) !== 40) {
            throw new \InvalidArgumentException('Invalid AWS Secret Access Key format');
        }
    }

    private function validateGcpCredentials(array $credentials): void
    {
        $serviceAccount = json_decode($credentials['service_account_json'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON format for GCP service account');
        }

        $requiredFields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
        foreach ($requiredFields as $field) {
            if (! isset($serviceAccount[$field])) {
                throw new \InvalidArgumentException("Missing required field in service account JSON: {$field}");
            }
        }
    }

    private function validateAzureCredentials(array $credentials): void
    {
        // Basic UUID format validation for Azure IDs
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        if (! preg_match($uuidPattern, $credentials['subscription_id'])) {
            throw new \InvalidArgumentException('Invalid Azure Subscription ID format');
        }

        if (! preg_match($uuidPattern, $credentials['client_id'])) {
            throw new \InvalidArgumentException('Invalid Azure Client ID format');
        }

        if (! preg_match($uuidPattern, $credentials['tenant_id'])) {
            throw new \InvalidArgumentException('Invalid Azure Tenant ID format');
        }
    }

    private function validateDigitalOceanCredentials(array $credentials): void
    {
        // DigitalOcean API tokens are 64 characters long
        if (strlen($credentials['api_token']) !== 64) {
            throw new \InvalidArgumentException('Invalid DigitalOcean API token format');
        }
    }

    private function validateHetznerCredentials(array $credentials): void
    {
        // Hetzner API tokens start with specific prefixes
        if (! str_starts_with($credentials['api_token'], 'hcloud_')) {
            throw new \InvalidArgumentException('Invalid Hetzner API token format');
        }
    }

    private function validateLinodeCredentials(array $credentials): void
    {
        // Linode API tokens are typically 64 characters
        if (strlen($credentials['api_token']) < 32) {
            throw new \InvalidArgumentException('Invalid Linode API token format');
        }
    }

    private function validateVultrCredentials(array $credentials): void
    {
        // Vultr API keys are typically 32 characters
        if (strlen($credentials['api_key']) !== 32) {
            throw new \InvalidArgumentException('Invalid Vultr API key format');
        }
    }

    // Validation Status Methods
    public function markAsValidated(): void
    {
        $this->last_validated_at = now();
        $this->is_active = true;
        $this->save();
    }

    public function markAsInvalid(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public function isValidated(): bool
    {
        return $this->last_validated_at !== null && $this->is_active;
    }

    public function needsValidation(): bool
    {
        if (! $this->last_validated_at) {
            return true;
        }

        // Re-validate every 24 hours
        return $this->last_validated_at->isBefore(now()->subDay());
    }

    // Region Methods
    public function getAvailableRegions(): array
    {
        return match ($this->provider_name) {
            'aws' => [
                'us-east-1' => 'US East (N. Virginia)',
                'us-east-2' => 'US East (Ohio)',
                'us-west-1' => 'US West (N. California)',
                'us-west-2' => 'US West (Oregon)',
                'eu-west-1' => 'Europe (Ireland)',
                'eu-west-2' => 'Europe (London)',
                'eu-central-1' => 'Europe (Frankfurt)',
                'ap-southeast-1' => 'Asia Pacific (Singapore)',
                'ap-southeast-2' => 'Asia Pacific (Sydney)',
                'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            ],
            'gcp' => [
                'us-central1' => 'US Central (Iowa)',
                'us-east1' => 'US East (South Carolina)',
                'us-west1' => 'US West (Oregon)',
                'europe-west1' => 'Europe West (Belgium)',
                'europe-west2' => 'Europe West (London)',
                'asia-east1' => 'Asia East (Taiwan)',
                'asia-southeast1' => 'Asia Southeast (Singapore)',
            ],
            'azure' => [
                'eastus' => 'East US',
                'westus' => 'West US',
                'westeurope' => 'West Europe',
                'eastasia' => 'East Asia',
                'southeastasia' => 'Southeast Asia',
            ],
            'digitalocean' => [
                'nyc1' => 'New York 1',
                'nyc3' => 'New York 3',
                'ams3' => 'Amsterdam 3',
                'sfo3' => 'San Francisco 3',
                'sgp1' => 'Singapore 1',
                'lon1' => 'London 1',
                'fra1' => 'Frankfurt 1',
                'tor1' => 'Toronto 1',
                'blr1' => 'Bangalore 1',
            ],
            'hetzner' => [
                'nbg1' => 'Nuremberg',
                'fsn1' => 'Falkenstein',
                'hel1' => 'Helsinki',
                'ash' => 'Ashburn',
            ],
            default => [],
        };
    }

    public function setRegion(string $region): void
    {
        $availableRegions = $this->getAvailableRegions();

        if (! empty($availableRegions) && ! array_key_exists($region, $availableRegions)) {
            throw new \InvalidArgumentException("Invalid region '{$region}' for provider '{$this->provider_name}'");
        }

        $this->provider_region = $region;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider_name', $provider);
    }

    public function scopeValidated($query)
    {
        return $query->whereNotNull('last_validated_at')->where('is_active', true);
    }

    public function scopeNeedsValidation($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_validated_at')
                ->orWhere('last_validated_at', '<', now()->subDay());
        });
    }
}
