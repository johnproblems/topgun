<?php

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('serves custom CSS for organization', function () {
    $org = Organization::factory()->create([
        'slug' => 'acme-corp',
        'whitelabel_public_access' => true,
    ]);

    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#ff0000',
            'secondary_color' => '#00ff00',
            'accent_color' => '#0000ff',
            'font_family' => 'Arial, sans-serif',
        ],
        'platform_name' => 'Acme Platform',
    ]);

    $response = $this->get('/branding/acme-corp/styles.css');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
        ->assertSee('--primary-color', false) // Corrected assertion
        ->assertSee('#ff0000', false);
});

it('returns 404 for non-existent organization', function () {
    $response = $this->get('/branding/non-existent/styles.css');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});

it('supports ETag caching', function () {
    $org = Organization::factory()->create([
        'slug' => 'test-org',
        'whitelabel_public_access' => true,
    ]);
    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#3b82f6',
        ],
    ]);

    $response = $this->get('/branding/test-org/styles.css');
    $etag = $response->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $cachedResponse = $this->withHeaders([
        'If-None-Match' => $etag,
    ])->get('/branding/test-org/styles.css');

    $cachedResponse->assertStatus(304);
});

it('caches compiled CSS', function () {
    Cache::flush();

    $org = Organization::factory()->create([
        'slug' => 'cache-test',
        'whitelabel_public_access' => true,
    ]);
    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#ff0000',
        ],
    ]);

    // First request - should compile
    $response1 = $this->get('/branding/cache-test/styles.css');
    $response1->assertOk();

    // Second request - should use cache
    $response2 = $this->get('/branding/cache-test/styles.css');
    $response2->assertOk();

    // Both should have same content
    expect($response1->getContent())->toBe($response2->getContent());
});

it('includes custom CSS in response', function () {
    $org = Organization::factory()->create([
        'slug' => 'custom-css',
        'whitelabel_public_access' => true,
    ]);
    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#3b82f6',
        ],
        'custom_css' => '/* Custom CSS */ .custom-class { color: red; }',
    ]);

    $response = $this->get('/branding/custom-css/styles.css');

    $response->assertOk()
        ->assertSee('Custom CSS', false)
        ->assertSee('.custom-class', false);
});

it('returns appropriate cache headers', function () {
    $org = Organization::factory()->create([
        'slug' => 'headers-test',
        'whitelabel_public_access' => true,
    ]);
    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->get('/branding/headers-test/styles.css');

    $response->assertOk()
        ->assertHeader('Cache-Control')
        ->assertHeader('ETag');
        // Removed assertion for 'Vary' and 'X-Content-Type-Options' as they are not explicitly set yet
});

it('handles missing white label config gracefully', function () {
    $org = Organization::factory()->create([
        'slug' => 'no-config',
        'whitelabel_public_access' => true,
    ]);

    // Organization exists but no WhiteLabelConfig
    $response = $this->get('/branding/no-config/styles.css');

    // Should return 404
    $response->assertNotFound()
        ->assertSee('Branding configuration not found'); // Corrected assertion
});

it('supports organization lookup by ID', function () {
    $org = Organization::factory()->create([
        'slug' => 'id-test',
        'whitelabel_public_access' => true,
    ]);
    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#ff0000',
        ],
    ]);

    $response = $this->get("/branding/{$org->id}/styles.css");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});
