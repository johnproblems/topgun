<?php

namespace Tests\Feature\Enterprise;

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BrandingPerformanceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertLessThan(0.5, $endTime - $startTime, 'CSS compilation time should be less than 500ms');
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
        $this->assertLessThan(0.1, $endTime - $startTime, 'Cached response time should be less than 100ms');
    }

    public function test_minification_reduces_size()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => '#ff0000',
            ],
            'custom_css' => 'body { /* this is a comment */ color: red; }',
        ]);

        // Get non-minified CSS
        config(['app.env' => 'local']);
        $responseUnminified = $this->get("/branding/{$org->slug}/styles.css");
        $unminifiedSize = strlen($responseUnminified->content());

        // Get minified CSS
        putenv('APP_ENV=production');
        $responseMinified = $this->get("/branding/{$org->slug}/styles.css");
        $minifiedSize = strlen($responseMinified->content());
        putenv('APP_ENV=testing');

        $this->assertTrue($minifiedSize < $unminifiedSize);
        $this->assertTrue(($unminifiedSize - $minifiedSize) / $unminifiedSize > 0.3, 'Minification should reduce size by more than 30%');
    }

    public function test_cache_hit_ratio()
    {
        $org = Organization::factory()->create(['whitelabel_public_access' => true]);
        $config = WhiteLabelConfig::factory()->create([
            'organization_id' => $org->id,
            'theme_config' => [
                'primary_color' => '#ff0000',
            ],
        ]);

        Cache::flush();

        $cacheKey = $this->getCacheKey($org->slug, $config->updated_at->timestamp);

        // 1 miss
        $this->get("/branding/{$org->slug}/styles.css");
        $this->assertFalse(Cache::has($cacheKey));

        // 9 hits
        for ($i = 0; $i < 9; $i++) {
            $this->get("/branding/{$org->slug}/styles.css");
            $this->assertTrue(Cache::has($cacheKey));
        }

        // This is a simplified test. A real cache hit ratio would be measured in a monitoring tool.
        // We are just asserting that the cache is being used.
        $this->assertTrue(true);
    }

    private function getCacheKey(string $organizationSlug, int $updatedTimestamp = 0): string
    {
        return sprintf(
            '%s:%s:css:%s:%d',
            'branding',
            $organizationSlug,
            'v1',
            $updatedTimestamp
        );
    }
}
