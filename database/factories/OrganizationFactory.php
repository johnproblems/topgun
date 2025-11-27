<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'hierarchy_type' => $this->faker->randomElement(['top_branch', 'master_branch', 'sub_user', 'end_user']),
            'hierarchy_level' => 0,
            'parent_organization_id' => null,
            'branding_config' => [],
            'feature_flags' => [],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the organization is a top branch.
     */
    public function topBranch(): static
    {
        return $this->state(fn (array $attributes) => [
            'hierarchy_type' => 'top_branch',
            'hierarchy_level' => 0,
            'parent_organization_id' => null,
        ]);
    }

    /**
     * Indicate that the organization is a master branch.
     */
    public function masterBranch(): static
    {
        return $this->state(fn (array $attributes) => [
            'hierarchy_type' => 'master_branch',
            'hierarchy_level' => 1,
        ]);
    }

    /**
     * Indicate that the organization is a sub user.
     */
    public function subUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'hierarchy_type' => 'sub_user',
            'hierarchy_level' => 2,
        ]);
    }

    /**
     * Indicate that the organization is an end user.
     */
    public function endUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'hierarchy_type' => 'end_user',
            'hierarchy_level' => 3,
        ]);
    }

    /**
     * Indicate that the organization is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a parent organization.
     */
    public function withParent(Organization $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_organization_id' => $parent->id,
            'hierarchy_level' => $parent->hierarchy_level + 1,
        ]);
    }
}
