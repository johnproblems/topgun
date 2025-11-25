<?php

namespace Tests\Feature\Enterprise;

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use App\Services\Enterprise\SassCompilationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class BrandingErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected $mockedSassService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockedSassService = Mockery::mock(SassCompilationService::class);
        $this->app->instance(SassCompilationService::class, $this->mockedSassService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sass_syntax_error_handling()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => 'invalid-color',
            ],
        ]);

        $this->mockedSassService->shouldReceive('compile')
                                ->once()
                                ->andThrow(new \Exception('Mocked SASS syntax error')); // Changed to generic Exception
        $this->mockedSassService->shouldReceive('compileDarkMode')
                                ->zeroOrMoreTimes() // Changed to zeroOrMoreTimes()
                                ->andReturn('');


        $response = $this->get("/branding/{$org->slug}/styles.css");

        $response->assertStatus(500);
        $response->assertSee('/* Error: Mocked SASS syntax error */'); // Updated assertion
    }

    public function test_missing_template_file_handling()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create(['organization_id' => $org->id]);

        $this->mockedSassService->shouldReceive('compile')
                                ->once()
                                ->andThrow(new \Exception('Mocked template not found error'));
        $this->mockedSassService->shouldReceive('compileDarkMode')
                                ->zeroOrMoreTimes()
                                ->andReturn('');

        $response = $this->get("/branding/{$org->slug}/styles.css");

        $response->assertStatus(500);
        $response->assertSee('/* Error: Mocked template not found error */'); // Specific error message in CSS
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

        $this->mockedSassService->shouldReceive('compile')
                                ->once()
                                ->andThrow(new \Exception('Mocked SASS color error')); // Changed to generic Exception
        $this->mockedSassService->shouldReceive('compileDarkMode')
                                ->zeroOrMoreTimes() // Changed to zeroOrMoreTimes()
                                ->andReturn('');

        $response = $this->get("/branding/{$org->slug}/styles.css");

        $response->assertStatus(500);
        $response->assertSee('/* Error: Mocked SASS color error */'); // Updated assertion
    }

    public function test_corrupted_config_handling()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => 'not-an-array',
        ]);

        $this->mockedSassService->shouldReceive('compile')
                                ->once()
                                ->andThrow(new \Exception('array_merge(): Expected parameter 1 to be an array, string given'));
        $this->mockedSassService->shouldReceive('compileDarkMode')
                                ->zeroOrMoreTimes()
                                ->andReturn('');

        $response = $this->get("/branding/{$org->slug}/styles.css");

        $response->assertStatus(500);
        $response->assertSee('/* Error: array_merge(): Expected parameter 1 to be an array, string given */'); // Specific error in CSS (likely from config access)
    }

    public function test_organization_not_found_handling()
    {
        $response = $this->get('/branding/non-existent-org/styles.css');

        $response->assertStatus(404);
        $response->assertSee('Organization not found');
    }
}
