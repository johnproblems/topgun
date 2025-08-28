<?php

namespace Database\Factories;

use App\Models\EnterpriseLicense;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EnterpriseLicense>
 */
class EnterpriseLicenseFactory extends Factory
{
    protected $model = EnterpriseLicense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'license_key' => 'CL-'.Str::upper(Str::random(32)),
            'license_type' => $this->faker->randomElement(['perpetual', 'subscription', 'trial']),
            'license_tier' => $this->faker->randomElement(['basic', 'professional', 'enterprise']),
            'features' => [
                'infrastructure_provisioning',
                'domain_management',
                'white_label_branding',
            ],
            'limits' => [
                'max_users' => $this->faker->numberBetween(5, 100),
                'max_servers' => $this->faker->numberBetween(10, 500),
                'max_domains' => $this->faker->numberBetween(5, 50),
            ],
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'last_validated_at' => now(),
            'authorized_domains' => [
                $this->faker->domainName(),
                $this->faker->domainName(),
            ],
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the license is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
            'status' => 'expired',
        ]);
    }

    /**
     * Indicate that the license is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the license is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
        ]);
    }

    /**
     * Indicate that the license is a trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'license_type' => 'trial',
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Indicate that the license is perpetual.
     */
    public function perpetual(): static
    {
        return $this->state(fn (array $attributes) => [
            'license_type' => 'perpetual',
            'expires_at' => null,
        ]);
    }

    /**
     * Set specific features for the license.
     */
    public function withFeatures(array $features): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $features,
        ]);
    }

    /**
     * Set specific limits for the license.
     */
    public function withLimits(array $limits): static
    {
        return $this->state(fn (array $attributes) => [
            'limits' => $limits,
        ]);
    }

    /**
     * Set authorized domains for the license.
     */
    public function withDomains(array $domains): static
    {
        return $this->state(fn (array $attributes) => [
            'authorized_domains' => $domains,
        ]);
    }
}
