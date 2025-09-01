<?php

namespace Database\Factories;

use App\Models\CloudProviderCredential;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CloudProviderCredential>
 */
class CloudProviderCredentialFactory extends Factory
{
    protected $model = CloudProviderCredential::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = $this->faker->randomElement(['aws', 'gcp', 'azure', 'digitalocean', 'hetzner']);

        return [
            'organization_id' => Organization::factory(),
            'provider_name' => $provider,
            'provider_region' => $this->getRegionForProvider($provider),
            'credentials' => $this->getCredentialsForProvider($provider),
            'is_active' => true,
            'last_validated_at' => now(),
        ];
    }

    /**
     * Get sample region for a provider.
     */
    protected function getRegionForProvider(string $provider): ?string
    {
        return match ($provider) {
            'aws' => $this->faker->randomElement(['us-east-1', 'us-west-2', 'eu-west-1', 'ap-southeast-1']),
            'gcp' => $this->faker->randomElement(['us-central1', 'us-east1', 'europe-west1', 'asia-southeast1']),
            'azure' => $this->faker->randomElement(['East US', 'West US 2', 'West Europe', 'Southeast Asia']),
            default => null,
        };
    }

    /**
     * Get sample credentials for a provider.
     */
    protected function getCredentialsForProvider(string $provider): array
    {
        return match ($provider) {
            'aws' => [
                'access_key_id' => 'AKIA'.strtoupper($this->faker->bothify('??????????????')),
                'secret_access_key' => $this->faker->bothify('????????????????????????????????????????'),
            ],
            'gcp' => [
                'project_id' => $this->faker->slug(),
                'service_account_key' => json_encode([
                    'type' => 'service_account',
                    'project_id' => $this->faker->slug(),
                    'private_key_id' => $this->faker->uuid(),
                    'private_key' => '-----BEGIN PRIVATE KEY-----\n'.$this->faker->text(1000).'\n-----END PRIVATE KEY-----\n',
                    'client_email' => $this->faker->email(),
                    'client_id' => $this->faker->numerify('###############'),
                ]),
            ],
            'azure' => [
                'subscription_id' => $this->faker->uuid(),
                'client_id' => $this->faker->uuid(),
                'client_secret' => $this->faker->bothify('????????????????????????'),
                'tenant_id' => $this->faker->uuid(),
            ],
            'digitalocean' => [
                'api_token' => 'dop_v1_'.$this->faker->bothify('????????????????????????????????'),
            ],
            'hetzner' => [
                'api_token' => $this->faker->bothify('????????????????????????????????'),
            ],
            default => [],
        };
    }

    /**
     * Indicate that the credential is for AWS.
     */
    public function aws(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_name' => 'aws',
            'encrypted_credentials' => $this->getCredentialsForProvider('aws'),
        ]);
    }

    /**
     * Indicate that the credential is for GCP.
     */
    public function gcp(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_name' => 'gcp',
            'encrypted_credentials' => $this->getCredentialsForProvider('gcp'),
        ]);
    }

    /**
     * Indicate that the credential is for Azure.
     */
    public function azure(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_name' => 'azure',
            'encrypted_credentials' => $this->getCredentialsForProvider('azure'),
        ]);
    }

    /**
     * Indicate that the credential is for DigitalOcean.
     */
    public function digitalocean(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_name' => 'digitalocean',
            'encrypted_credentials' => $this->getCredentialsForProvider('digitalocean'),
        ]);
    }

    /**
     * Indicate that the credential is for Hetzner.
     */
    public function hetzner(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_name' => 'hetzner',
            'encrypted_credentials' => $this->getCredentialsForProvider('hetzner'),
        ]);
    }

    /**
     * Indicate that the credential is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set custom credentials.
     */
    public function withCredentials(array $credentials): static
    {
        return $this->state(fn (array $attributes) => [
            'encrypted_credentials' => $credentials,
        ]);
    }
}
