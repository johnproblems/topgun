<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhiteLabelConfig>
 */
class WhiteLabelConfigFactory extends Factory
{
    protected $model = WhiteLabelConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'platform_name' => $this->faker->company().' Platform',
            'theme_config' => [
                'primary_color' => $this->faker->hexColor(),
                'secondary_color' => $this->faker->hexColor(),
                'accent_color' => $this->faker->hexColor(),
                'background_color' => '#ffffff',
                'text_color' => '#000000',
            ],
            'logo_url' => $this->faker->imageUrl(200, 100, 'business'),
            'custom_css' => '',
            'custom_domains' => [
                $this->faker->domainName(),
            ],
            'custom_email_templates' => [],
            'hide_coolify_branding' => false,
        ];
    }

    /**
     * Set custom theme colors.
     */
    public function withTheme(array $colors): static
    {
        return $this->state(fn (array $attributes) => [
            'theme_config' => array_merge($attributes['theme_config'] ?? [], $colors),
        ]);
    }

    /**
     * Set custom domains.
     */
    public function withDomains(array $domains): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_domains' => $domains,
        ]);
    }

    /**
     * Set custom CSS.
     */
    public function withCustomCss(string $css): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_css' => $css,
        ]);
    }
}
