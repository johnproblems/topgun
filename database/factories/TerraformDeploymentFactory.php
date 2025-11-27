<?php

namespace Database\Factories;

use App\Models\CloudProviderCredential;
use App\Models\Organization;
use App\Models\TerraformDeployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TerraformDeployment>
 */
class TerraformDeploymentFactory extends Factory
{
    protected $model = TerraformDeployment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'cloud_provider_credential_id' => CloudProviderCredential::factory(),
            'deployment_name' => $this->faker->words(2, true).' Deployment',
            'provider_type' => $this->faker->randomElement(['aws', 'gcp', 'azure', 'digitalocean', 'hetzner']),
            'deployment_config' => [
                'instance_type' => $this->faker->randomElement(['t3.micro', 't3.small', 't3.medium']),
                'region' => $this->faker->randomElement(['us-east-1', 'us-west-2', 'eu-west-1']),
                'disk_size' => $this->faker->numberBetween(20, 100),
                'instance_count' => $this->faker->numberBetween(1, 5),
            ],
            'terraform_state' => [],
            'status' => TerraformDeployment::STATUS_PENDING,
            'deployment_output' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the deployment is provisioning.
     */
    public function provisioning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TerraformDeployment::STATUS_PROVISIONING,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the deployment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TerraformDeployment::STATUS_COMPLETED,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'deployment_output' => [
                'server_ip' => $this->faker->ipv4(),
                'server_id' => $this->faker->uuid(),
                'ssh_key_fingerprint' => $this->faker->sha256(),
            ],
        ]);
    }

    /**
     * Indicate that the deployment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TerraformDeployment::STATUS_FAILED,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'error_message' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the deployment is destroying.
     */
    public function destroying(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TerraformDeployment::STATUS_DESTROYING,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the deployment is destroyed.
     */
    public function destroyed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TerraformDeployment::STATUS_DESTROYED,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Set specific deployment configuration.
     */
    public function withConfig(array $config): static
    {
        return $this->state(fn (array $attributes) => [
            'deployment_config' => array_merge($attributes['deployment_config'] ?? [], $config),
        ]);
    }

    /**
     * Set specific provider type.
     */
    public function forProvider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_type' => $provider,
        ]);
    }

    /**
     * Set terraform state.
     */
    public function withState(array $state): static
    {
        return $this->state(fn (array $attributes) => [
            'terraform_state' => $state,
        ]);
    }
}
