<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use App\Services\Enterprise\CssValidationService;
use App\Services\Enterprise\SassCompilationService;
use App\Services\Enterprise\WhiteLabelService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ScssPhp\ScssPhp\Exception\SassException;

class DynamicAssetController extends Controller
{
    private const CACHE_VERSION = 'v1';

    private const CACHE_PREFIX = 'branding';

    private const CUSTOM_CSS_COMMENT = '/* Custom CSS */';

    private const ORG_LOOKUP_CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private WhiteLabelService $whiteLabelService, // This could be further refactored, but is ok for now.
        private CssValidationService $cssValidator,
        private SassCompilationService $sassService
    ) {}

    /**
     * Generate and serve organization-specific CSS
     *
     * @param  string  $organization  Organization slug or ID
     * @throws \ScssPhp\ScssPhp\Exception\SassException
     * @throws \Exception
     */
    public function styles(string $organization): Response
    {
        try {
            // 1. Find organization (with caching)
            // This is a performance-critical step, so we cache the lookup.
            /** @var Organization $organizationModel */
            $organizationModel = $this->findOrganization($organization);

            if (! $organizationModel) {
                return $this->errorResponse('not-found: Organization not found', 404);
            }

            // 2. Check authorization
            // This ensures that only authorized users can access the branding.
            if (! $this->canAccessBranding($organizationModel)) {
                return $this->unauthorizedResponse();
            }

            // 3. Get white-label configuration (eager loaded)
            // The 'whiteLabelConfig' relation is eager loaded in findOrganization().
            $config = $organizationModel->whiteLabelConfig;
            if (! $config) {
                return $this->errorResponse('not-found: Branding configuration not found', 404);
            }

            // 4. Check cache
            // We use the organization slug and the last update timestamp to create a unique cache key.
            $cacheKey = $this->getCacheKey($organizationModel->slug, $config->updated_at?->timestamp ?? 0);
            $etag = $this->generateEtag($config);

            // Handle If-None-Match header for 304 responses
            // This is a browser-level cache that avoids re-downloading the CSS if it hasn't changed.
            $request = request();
            if ($request->header('If-None-Match') === $etag) {
                return response('', 304);
            }

            // Try to get from cache
            // If the CSS is not in the cache, we build it and store it.
            $ttl = config('coolify.white_label_cache_ttl', 3600);
            $css = Cache::remember($cacheKey, $ttl, fn () => $this->buildCssResponse($config));

            // 6. Return response with caching headers
            // We add the ETag and a custom header to indicate if the response was served from cache.
            return response($css, 200)
                ->header('Content-Type', 'text/css; charset=UTF-8')
                ->header('ETag', $etag)
                ->header('X-Cache-Hit', Cache::has($cacheKey) ? 'true' : 'false');

        } catch (SassException $e) {
            // Handle SASS compilation errors specifically.
            Log::error('SASS compilation failed', [
                'organization' => $organization ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('internal-server-error: Failed to compile branding styles', 500, "/* SASS Error: {$e->getMessage()} */");
        } catch (\Exception $e) {
            // Handle all other exceptions.
            Log::error('Branding CSS generation failed', [
                'organization' => $organization ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            return $this->errorResponse('internal-server-error: Failed to compile branding styles', 500, "/* Error: {$e->getMessage()} */");
        }
    }

    private function buildCssResponse(WhiteLabelConfig $config): string
    {
        $css = $this->sassService->compile($config);
        $darkModeCss = $this->sassService->compileDarkMode();
        $customCss = $this->cssValidator->sanitize($config->custom_css ?? '');

        $finalCss = [$css];
        if (! empty($darkModeCss)) {
            $finalCss[] = $darkModeCss;
        }
        if (! empty($customCss)) {
            $finalCss[] = self::CUSTOM_CSS_COMMENT;
            $finalCss[] = $customCss;
        }

        $cssString = implode("\n\n", $finalCss);

        return app()->environment('production') ? $this->minifyCss($cssString) : $cssString;
    }

    /**
     * Find organization by ID or slug (with caching)
     */
    private function findOrganization(string $identifier): ?Organization
    {
        $cacheKey = "org:lookup:{$identifier}";

        return Cache::remember($cacheKey, self::ORG_LOOKUP_CACHE_TTL, function () use ($identifier) {
            // Single optimized query
            return Organization::with('whiteLabelConfig')
                ->where(function ($query) use ($identifier) {
                    if (Str::isUuid($identifier)) {
                        $query->where('id', $identifier);
                    } else {
                        $query->where('slug', $identifier);
                    }
                })
                ->first();
        });
    }

    /**
     * Check if user can access organization branding
     */
    private function canAccessBranding(Organization $org): bool
    {
        // Public access allowed
        if ($org->whitelabel_public_access) {
            return true;
        }

        // Require authentication for private branding
        if (! auth()->check()) {
            return false;
        }

        // Check organization membership directly
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Check if user is a member of the organization
        return $org->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(): Response
    {
        return $this->errorResponse('unauthorized: Branding access requires authentication', 403);
    }

    /**
     * Generate consistent error response
     */
    private function errorResponse(string $message, int $status, ?string $fallbackCss = null): Response
    {
        $messageParts = explode(':', $message, 2);
        $cleanMessage = trim($messageParts[1] ?? $messageParts[0]);

        $css = $fallbackCss ?? sprintf(
            "/* Coolify Branding Error: %s (HTTP %d) */\n:root { --error: true; }",
            $cleanMessage,
            $status
        );

        return response($css, $status)
            ->header('Content-Type', 'text/css; charset=UTF-8')
            ->header('X-Branding-Error', strtolower(str_replace([' ', ':'], ['-', ''], $message)))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Get cache key for organization CSS
     */
    private function getCacheKey(string $organizationSlug, int $updatedTimestamp = 0): string
    {
        return sprintf(
            '%s:%s:css:%s:%d',
            self::CACHE_PREFIX,
            $organizationSlug,
            self::CACHE_VERSION,
            $updatedTimestamp
        );
    }

    /**
     * Generate ETag for cache validation
     */
    private function generateEtag(WhiteLabelConfig $config): string
    {
        $content = json_encode($config->theme_config).($config->custom_css ?? '');
        $hash = md5($content);

        return '"'.$hash.'"';
    }

    /**
     * Minify CSS for production
     */
    private function minifyCss(string $css): string
    {
        // Remove comments (preserving license comments)
        $css = preg_replace('/\/\*(?![!*])(.*?)\*\//s', '', $css);

        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);

        return trim($css);
    }
}
