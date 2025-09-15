<?php

namespace App\Services\Enterprise;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BrandingCacheService
{
    protected const CACHE_PREFIX = 'branding:';
    protected const THEME_CACHE_PREFIX = 'theme:';
    protected const DOMAIN_CACHE_PREFIX = 'domain:';
    protected const ASSET_CACHE_PREFIX = 'asset:';
    protected const CACHE_TTL = 86400; // 24 hours

    /**
     * Cache compiled theme CSS
     */
    public function cacheCompiledTheme(string $organizationId, string $css): void
    {
        $key = $this->getThemeCacheKey($organizationId);

        Cache::put($key, $css, self::CACHE_TTL);

        // Also store in Redis for faster retrieval
        if ($this->isRedisAvailable()) {
            Redis::setex($key, self::CACHE_TTL, $css);
        }

        // Store a hash for version tracking
        $this->cacheThemeVersion($organizationId, md5($css));
    }

    /**
     * Get cached compiled theme
     */
    public function getCachedTheme(string $organizationId): ?string
    {
        $key = $this->getThemeCacheKey($organizationId);

        // Try Redis first for better performance
        if ($this->isRedisAvailable()) {
            $cached = Redis::get($key);
            if ($cached) {
                return $cached;
            }
        }

        return Cache::get($key);
    }

    /**
     * Cache theme version hash for validation
     */
    protected function cacheThemeVersion(string $organizationId, string $hash): void
    {
        $key = self::CACHE_PREFIX . 'version:' . $organizationId;
        Cache::put($key, $hash, self::CACHE_TTL);
    }

    /**
     * Get cached theme version
     */
    public function getThemeVersion(string $organizationId): ?string
    {
        $key = self::CACHE_PREFIX . 'version:' . $organizationId;
        return Cache::get($key);
    }

    /**
     * Cache logo and asset URLs
     */
    public function cacheAssetUrl(string $organizationId, string $assetType, string $url): void
    {
        $key = $this->getAssetCacheKey($organizationId, $assetType);
        Cache::put($key, $url, self::CACHE_TTL);
    }

    /**
     * Get cached asset URL
     */
    public function getCachedAssetUrl(string $organizationId, string $assetType): ?string
    {
        $key = $this->getAssetCacheKey($organizationId, $assetType);
        return Cache::get($key);
    }

    /**
     * Cache domain-to-organization mapping
     */
    public function cacheDomainMapping(string $domain, string $organizationId): void
    {
        $key = self::DOMAIN_CACHE_PREFIX . $domain;

        Cache::put($key, $organizationId, self::CACHE_TTL);

        // Also store in Redis for faster domain resolution
        if ($this->isRedisAvailable()) {
            Redis::setex($key, self::CACHE_TTL, $organizationId);
        }
    }

    /**
     * Get organization ID from domain
     */
    public function getOrganizationByDomain(string $domain): ?string
    {
        $key = self::DOMAIN_CACHE_PREFIX . $domain;

        // Try Redis first
        if ($this->isRedisAvailable()) {
            $orgId = Redis::get($key);
            if ($orgId) {
                return $orgId;
            }
        }

        return Cache::get($key);
    }

    /**
     * Cache branding configuration
     */
    public function cacheBrandingConfig(string $organizationId, array $config): void
    {
        $key = self::CACHE_PREFIX . 'config:' . $organizationId;

        Cache::put($key, $config, self::CACHE_TTL);

        // Store individual config elements for partial retrieval
        foreach ($config as $configKey => $value) {
            $elementKey = self::CACHE_PREFIX . "config:{$organizationId}:{$configKey}";
            Cache::put($elementKey, $value, self::CACHE_TTL);
        }
    }

    /**
     * Get cached branding configuration
     */
    public function getCachedBrandingConfig(string $organizationId, ?string $configKey = null): mixed
    {
        if ($configKey) {
            $key = self::CACHE_PREFIX . "config:{$organizationId}:{$configKey}";
            return Cache::get($key);
        }

        $key = self::CACHE_PREFIX . 'config:' . $organizationId;
        return Cache::get($key);
    }

    /**
     * Clear all cache for an organization
     */
    public function clearOrganizationCache(string $organizationId): void
    {
        // Clear theme cache
        Cache::forget($this->getThemeCacheKey($organizationId));
        Cache::forget(self::CACHE_PREFIX . 'version:' . $organizationId);
        Cache::forget(self::CACHE_PREFIX . 'config:' . $organizationId);

        // Clear asset caches
        $this->clearAssetCache($organizationId);

        // Clear from Redis if available
        if ($this->isRedisAvailable()) {
            $pattern = self::CACHE_PREFIX . "*{$organizationId}*";
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                Redis::del($keys);
            }
        }

        // Trigger cache warming in background
        $this->warmCache($organizationId);
    }

    /**
     * Clear cache for a specific domain
     */
    public function clearDomainCache(string $domain): void
    {
        $key = self::DOMAIN_CACHE_PREFIX . $domain;

        Cache::forget($key);

        if ($this->isRedisAvailable()) {
            Redis::del($key);
        }
    }

    /**
     * Clear asset cache for organization
     */
    protected function clearAssetCache(string $organizationId): void
    {
        $assetTypes = ['logo', 'favicon', 'favicon-16', 'favicon-32', 'favicon-64', 'favicon-128', 'favicon-192'];

        foreach ($assetTypes as $type) {
            Cache::forget($this->getAssetCacheKey($organizationId, $type));
        }
    }

    /**
     * Warm cache for organization (background job)
     */
    public function warmCache(string $organizationId): void
    {
        // This would typically dispatch a background job
        // to pre-generate and cache theme CSS and assets
        dispatch(function () use ($organizationId) {
            // Fetch WhiteLabelConfig and regenerate cache
            $config = \App\Models\WhiteLabelConfig::where('organization_id', $organizationId)->first();
            if ($config) {
                app(WhiteLabelService::class)->compileTheme($config);
            }
        })->afterResponse();
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(string $organizationId): array
    {
        $stats = [
            'theme_cached' => (bool) $this->getCachedTheme($organizationId),
            'theme_version' => $this->getThemeVersion($organizationId),
            'logo_cached' => (bool) $this->getCachedAssetUrl($organizationId, 'logo'),
            'config_cached' => (bool) $this->getCachedBrandingConfig($organizationId),
            'cache_size' => 0,
        ];

        // Calculate approximate cache size
        if ($theme = $this->getCachedTheme($organizationId)) {
            $stats['cache_size'] += strlen($theme);
        }

        if ($config = $this->getCachedBrandingConfig($organizationId)) {
            $stats['cache_size'] += strlen(serialize($config));
        }

        $stats['cache_size_formatted'] = $this->formatBytes($stats['cache_size']);

        return $stats;
    }

    /**
     * Invalidate cache based on patterns
     */
    public function invalidateByPattern(string $pattern): int
    {
        $count = 0;

        if ($this->isRedisAvailable()) {
            $keys = Redis::keys(self::CACHE_PREFIX . $pattern);
            if (!empty($keys)) {
                $count = Redis::del($keys);
            }
        }

        // Also clear from Laravel cache
        // Note: This requires cache tags support
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['branding'])->flush();
        }

        return $count;
    }

    /**
     * Cache compiled CSS with versioning
     */
    public function cacheCompiledCss(string $organizationId, string $css, array $metadata = []): void
    {
        $version = $metadata['version'] ?? time();
        $key = self::THEME_CACHE_PREFIX . "{$organizationId}:v{$version}";

        // Store with version
        Cache::put($key, $css, self::CACHE_TTL);

        // Update current version pointer
        Cache::put(self::THEME_CACHE_PREFIX . "{$organizationId}:current", $version, self::CACHE_TTL);

        // Store metadata
        if (!empty($metadata)) {
            Cache::put(self::THEME_CACHE_PREFIX . "{$organizationId}:meta", $metadata, self::CACHE_TTL);
        }
    }

    /**
     * Get current CSS version
     */
    public function getCurrentCssVersion(string $organizationId): ?string
    {
        $version = Cache::get(self::THEME_CACHE_PREFIX . "{$organizationId}:current");

        if ($version) {
            return Cache::get(self::THEME_CACHE_PREFIX . "{$organizationId}:v{$version}");
        }

        return null;
    }

    /**
     * Helper: Get theme cache key
     */
    protected function getThemeCacheKey(string $organizationId): string
    {
        return self::THEME_CACHE_PREFIX . $organizationId;
    }

    /**
     * Helper: Get asset cache key
     */
    protected function getAssetCacheKey(string $organizationId, string $assetType): string
    {
        return self::ASSET_CACHE_PREFIX . "{$organizationId}:{$assetType}";
    }

    /**
     * Helper: Check if Redis is available
     */
    protected function isRedisAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper: Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}