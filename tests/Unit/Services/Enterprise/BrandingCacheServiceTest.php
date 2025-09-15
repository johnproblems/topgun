<?php

namespace Tests\Unit\Services\Enterprise;

use App\Services\Enterprise\BrandingCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BrandingCacheServiceTest extends TestCase
{
    protected BrandingCacheService $service;
    protected string $organizationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BrandingCacheService();
        $this->organizationId = 'test-org-' . uniqid();

        Cache::flush();
    }

    public function test_cache_compiled_theme_stores_css()
    {
        $css = '.test { color: red; }';

        $this->service->cacheCompiledTheme($this->organizationId, $css);

        $cached = $this->service->getCachedTheme($this->organizationId);
        $this->assertEquals($css, $cached);
    }

    public function test_cache_theme_version_stores_hash()
    {
        $css = '.test { color: blue; }';
        $expectedHash = md5($css);

        $this->service->cacheCompiledTheme($this->organizationId, $css);

        $version = $this->service->getThemeVersion($this->organizationId);
        $this->assertEquals($expectedHash, $version);
    }

    public function test_cache_asset_url_stores_and_retrieves()
    {
        $logoUrl = 'https://example.com/logo.png';

        $this->service->cacheAssetUrl($this->organizationId, 'logo', $logoUrl);

        $cached = $this->service->getCachedAssetUrl($this->organizationId, 'logo');
        $this->assertEquals($logoUrl, $cached);
    }

    public function test_cache_domain_mapping()
    {
        $domain = 'test.example.com';

        $this->service->cacheDomainMapping($domain, $this->organizationId);

        $cached = $this->service->getOrganizationByDomain($domain);
        $this->assertEquals($this->organizationId, $cached);
    }

    public function test_cache_branding_config_stores_array()
    {
        $config = [
            'platform_name' => 'Test Platform',
            'primary_color' => '#ff0000',
        ];

        $this->service->cacheBrandingConfig($this->organizationId, $config);

        $cached = $this->service->getCachedBrandingConfig($this->organizationId);
        $this->assertEquals($config, $cached);
    }

    public function test_cache_branding_config_retrieves_specific_key()
    {
        $config = [
            'platform_name' => 'Test Platform',
            'primary_color' => '#ff0000',
        ];

        $this->service->cacheBrandingConfig($this->organizationId, $config);

        $platformName = $this->service->getCachedBrandingConfig($this->organizationId, 'platform_name');
        $this->assertEquals('Test Platform', $platformName);
    }

    public function test_clear_organization_cache_removes_all_entries()
    {
        // Cache various items
        $this->service->cacheCompiledTheme($this->organizationId, '.test{}');
        $this->service->cacheAssetUrl($this->organizationId, 'logo', 'logo.png');
        $this->service->cacheBrandingConfig($this->organizationId, ['test' => 'data']);

        // Clear cache
        $this->service->clearOrganizationCache($this->organizationId);

        // Verify all cleared
        $this->assertNull($this->service->getCachedTheme($this->organizationId));
        $this->assertNull($this->service->getCachedAssetUrl($this->organizationId, 'logo'));
        $this->assertNull($this->service->getCachedBrandingConfig($this->organizationId));
    }

    public function test_clear_domain_cache_removes_mapping()
    {
        $domain = 'test.example.com';

        $this->service->cacheDomainMapping($domain, $this->organizationId);
        $this->service->clearDomainCache($domain);

        $cached = $this->service->getOrganizationByDomain($domain);
        $this->assertNull($cached);
    }

    public function test_get_cache_stats_returns_metrics()
    {
        $css = '.test { color: red; }';
        $config = ['platform_name' => 'Test'];

        $this->service->cacheCompiledTheme($this->organizationId, $css);
        $this->service->cacheAssetUrl($this->organizationId, 'logo', 'logo.png');
        $this->service->cacheBrandingConfig($this->organizationId, $config);

        $stats = $this->service->getCacheStats($this->organizationId);

        $this->assertTrue($stats['theme_cached']);
        $this->assertTrue($stats['logo_cached']);
        $this->assertTrue($stats['config_cached']);
        $this->assertGreaterThan(0, $stats['cache_size']);
        $this->assertArrayHasKey('cache_size_formatted', $stats);
    }

    public function test_cache_compiled_css_with_versioning()
    {
        $css = '.test { color: green; }';
        $metadata = ['version' => 123, 'compiled_at' => now()->toIso8601String()];

        $this->service->cacheCompiledCss($this->organizationId, $css, $metadata);

        $cached = $this->service->getCurrentCssVersion($this->organizationId);
        $this->assertEquals($css, $cached);
    }

    public function test_format_bytes_helper()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('formatBytes');
        $method->setAccessible(true);

        $this->assertEquals('100 B', $method->invoke($this->service, 100));
        $this->assertEquals('1 KB', $method->invoke($this->service, 1024));
        $this->assertEquals('1.5 KB', $method->invoke($this->service, 1536));
        $this->assertEquals('1 MB', $method->invoke($this->service, 1048576));
    }
}