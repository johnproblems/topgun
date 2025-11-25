<?php

namespace Tests\Feature\Enterprise;

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use App\Services\Enterprise\SassCompilationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class BrandingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_css_compilation_time_is_within_limits()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => '#ff0000',
            ],
        ]);

        $startTime = microtime(true);
        $response = $this->get("/branding/{$org->slug}/styles.css");
        $endTime = microtime(true);

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $endTime - $startTime, 'CSS compilation time should be less than 1000ms');
    }

    public function test_cached_response_time_is_within_limits()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => '#ff0000',
            ],
        ]);

        // First request to cache the response
        $this->get("/branding/{$org->slug}/styles.css");

        $startTime = microtime(true);
        $response = $this->get("/branding/{$org->slug}/styles.css");
        $endTime = microtime(true);

        $response->assertStatus(200);
        $this->assertLessThan(0.2, $endTime - $startTime, 'Cached response time should be less than 200ms');
    }

    public function test_minification_reduces_size()
    {
        $controller = $this->app->make(\App\Http\Controllers\Enterprise\DynamicAssetController::class);
        $method = new \ReflectionMethod($controller, 'minifyCss');
        $method->setAccessible(true);

        $unminifiedCss = 'body { /* this is a comment */ color: red;  }';
        $minifiedCss = $method->invoke($controller, $unminifiedCss);

        $this->assertNotEquals($unminifiedCss, $minifiedCss, 'Minified CSS should be different from unminified CSS');
        $this->assertStringNotContainsString('/*', $minifiedCss, 'Minified CSS should not contain comments');
        $this->assertStringNotContainsString('  ', $minifiedCss, 'Minified CSS should not contain double spaces');
        $this->assertTrue(strlen($minifiedCss) < strlen($unminifiedCss), 'Minified CSS should be smaller than unminified CSS');
    }

    public function test_cache_hit_ratio()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => '#ff0000',
            ],
        ]);

        // Mock the SassCompilationService to count how many times 'compile' is called
        $mockedSassService = Mockery::mock(SassCompilationService::class);
        $mockedSassService->shouldReceive('compile')->times(1)->andReturn('/* Compiled CSS */');
        $mockedSassService->shouldReceive('compileDarkMode')->zeroOrMoreTimes()->andReturn('');
        $this->app->instance(SassCompilationService::class, $mockedSassService);

        // First request: should trigger compilation (cache miss)
        $this->get("/branding/{$org->slug}/styles.css");

        // Subsequent requests: should not trigger compilation (cache hits)
        for ($i = 0; $i < 9; $i++) {
            $this->get("/branding/{$org->slug}/styles.css");
        }

        // Assert that compile was called only once in total
        $mockedSassService->shouldHaveReceived('compile')->times(1);
        $this->assertTrue(true); // Dummy assertion to prevent risky test warning
    }
}
