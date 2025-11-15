<?php

namespace Tests\Feature\Enterprise;

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BrandingErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sass_syntax_error_handling()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => 'invalid-color',
            ],
        ]);

        $response = $this->get("/branding/{$org->slug}/styles.css");

        $response->assertStatus(500);
        $response->assertSee('Failed to compile branding styles');
    }

    public function test_missing_template_file_handling()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create(['organization_id' => $org->id]);

        // Temporarily rename the template file
        $templatePath = resource_path('sass/branding/theme.scss');
        $backupPath = resource_path('sass/branding/theme.scss.bak');
        File::move($templatePath, $backupPath);

        $response = $this->get("/branding/{$org->slug}/styles.css");

        // Restore the template file
        File::move($backupPath, $templatePath);

        $response->assertStatus(500);
        $response->assertSee('SASS template not found');
    }

    public function test_invalid_color_value_handling()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => 'not-a-color',
            ],
        ]);

        $response = $this->get("/branding/{$org->slug}/styles.css");

        $response->assertStatus(500);
        $response->assertSee('Failed to compile branding styles');
    }

    public function test_corrupted_config_handling()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => 'not-an-array',
        ]);

        $response = $this->get("/branding/{$org->slug}/styles.css");

        $response->assertStatus(500);
        $response->assertSee('Error');
    }

    public function test_organization_not_found_handling()
    {
        $response = $this->get('/branding/non-existent-org/styles.css');

        $response->assertStatus(404);
        $response->assertSee('Organization not found');
    }
}
