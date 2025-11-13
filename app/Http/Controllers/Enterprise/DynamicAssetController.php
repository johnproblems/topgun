<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Enterprise\CssValidationService;
use App\Services\Enterprise\WhiteLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\ValueConverter;

class DynamicAssetController extends Controller
{
    private const CACHE_VERSION = 'v1';
    private const CACHE_PREFIX = 'branding';
    private const CUSTOM_CSS_COMMENT = '/* Custom CSS */';
    private const ORG_LOOKUP_CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private WhiteLabelService $whiteLabelService,
        private CssValidationService $cssValidator
    ) {
    }

    /**
     * Generate and serve organization-specific CSS
     *
     * @param  string  $organization Organization slug or ID
     * @return Response
     */
    public function styles(string $organization): Response
    {
        try {
            // 1. Find organization (with caching)
            $organizationModel = $this->findOrganization($organization);

            if (! $organizationModel) {
                return $this->errorResponse('Organization not found', 404);
            }

            // 2. Check authorization
            if (! $this->canAccessBranding($organizationModel)) {
                return $this->unauthorizedResponse();
            }

            $organizationSlug = $organizationModel->slug ?? $organization;

            // 3. Get white-label configuration (eager loaded)
            $config = $this->whiteLabelService->getOrCreateConfig($organizationModel);
            $themeVariables = $this->whiteLabelService->getOrganizationThemeVariables($organizationModel);

            // 4. Check cache
            $cacheKey = $this->getCacheKey($organizationSlug, $config->updated_at?->timestamp ?? 0);
            $etag = $this->generateEtag($themeVariables, $config->custom_css);

            // Handle If-None-Match header for 304 responses
            $request = request();
            if ($request->header('If-None-Match') === $etag) {
                return response('', 304)
                    ->header('ETag', $etag)
                    ->header('Cache-Control', $this->getCacheControlHeader());
            }

            // Try to get from cache
            $css = Cache::get($cacheKey);

            if (! $css) {
                // 5. Compile SASS with organization variables
                $css = $this->compileSass($themeVariables, $config->custom_css ?? '');

                // Minify CSS in production
                if (app()->environment('production')) {
                    $css = $this->minifyCss($css);
                }

                // Cache the compiled CSS
                $ttl = config('enterprise.white_label.cache_ttl', 3600);
                Cache::put($cacheKey, $css, $ttl);
            }

            // 6. Return response with caching headers
            return response($css, 200)
                ->header('Content-Type', 'text/css; charset=UTF-8')
                ->header('Cache-Control', $this->getCacheControlHeader())
                ->header('ETag', $etag)
                ->header('Vary', 'Accept-Encoding')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (SassException $e) {
            Log::error('SASS compilation failed', [
                'organization' => $organization ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return default CSS as fallback
            return $this->errorResponse('SASS compilation failed', 500, $this->getDefaultCss());
        } catch (\Exception $e) {
            Log::error('CSS generation failed', [
                'organization' => $organization ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send to monitoring if available
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            return $this->errorResponse('Error generating CSS', 500);
        }
    }

    /**
     * Compile SASS template with organization variables
     *
     * @param  array<string, string>  $config
     * @param  string  $customCss
     * @return string
     */
    private function compileSass(array $config, string $customCss = ''): string
    {
        $compiler = new Compiler();

        // Set import paths
        $sassPath = resource_path('sass/enterprise');
        $compiler->setImportPaths([$sassPath]);

        // Enable source maps in debug mode
        if (config('enterprise.white_label.sass_debug', false)) {
            $compiler->setSourceMap(Compiler::SOURCE_MAP_INLINE);
        }

        // Set SASS variables using compiler's variable system (scssphp v2.0+ requires ValueConverter)
        $sassVariables = [];
        foreach ($config as $key => $value) {
            // Convert PHP array keys to SASS variable format
            $sassVar = str_replace('-', '_', $key);
            // Convert value using ValueConverter (scssphp v2.0+ requirement)
            $sassVariables[$sassVar] = ValueConverter::parseValue($this->formatSassValue($value));
        }
        $compiler->addVariables($sassVariables);

        // Load main SASS template
        $templatePath = $sassPath.'/white-label-template.scss';
        if (! file_exists($templatePath)) {
            throw new \RuntimeException("SASS template not found: {$templatePath}");
        }

        $sassContent = file_get_contents($templatePath);

        // Compile SASS to CSS
        try {
            $css = $compiler->compileString($sassContent)->getCss();
        } catch (SassException $e) {
            Log::error('SASS compilation error', [
                'error' => $e->getMessage(),
                'variables' => array_keys($sassVariables),
            ]);
            throw $e;
        }

        // Add dark mode styles if enabled
        if (($config['enable_dark_mode'] ?? false) || config('enterprise.white_label.default_theme.enable_dark_mode', false)) {
            $darkModeCss = $this->compileDarkModeSass($config);
            if (! empty($darkModeCss)) {
                $css .= "\n\n".$darkModeCss;
            }
        }

        // Append custom CSS if provided (sanitized)
        if (! empty($customCss)) {
            $sanitizedCss = $this->cssValidator->sanitize($customCss);
            $css .= "\n\n".self::CUSTOM_CSS_COMMENT."\n".$sanitizedCss;
        }

        return $css;
    }

    /**
     * Compile dark mode SASS template
     *
     * @param  array<string, string>  $config
     * @return string
     */
    private function compileDarkModeSass(array $config): string
    {
        $compiler = new Compiler();
        $sassPath = resource_path('sass/enterprise');
        $compiler->setImportPaths([$sassPath]);

        // Set SASS variables
        $sassVariables = [];
        foreach ($config as $key => $value) {
            $sassVar = str_replace('-', '_', $key);
            $sassVariables[$sassVar] = ValueConverter::parseValue($this->formatSassValue($value));
        }
        $compiler->addVariables($sassVariables);

        $templatePath = $sassPath.'/dark-mode-template.scss';
        if (! file_exists($templatePath)) {
            return '';
        }

        $sassContent = file_get_contents($templatePath);

        try {
            return $compiler->compileString($sassContent)->getCss();
        } catch (SassException $e) {
            Log::warning('Dark mode SASS compilation failed', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Format SASS value for compiler
     *
     * @param  string  $value
     * @return string
     */
    private function formatSassValue(string $value): string
    {
        $value = trim($value);

        // If it's a color value, return as-is
        if (preg_match('/^#[A-Fa-f0-9]{3,6}$/', $value)) {
            return $value;
        }

        // If it's already a quoted string, return as-is
        if (preg_match('/^["\'].*["\']$/', $value)) {
            return $value;
        }

        // For font families and other strings, wrap in quotes if needed
        if (str_contains($value, ',')) {
            // Font family with fallbacks - wrap in quotes
            return '"'.$value.'"';
        }

        // Return as-is (scssphp will handle it)
        return $value;
    }

    /**
     * Generate CSS custom properties string (fallback method)
     *
     * @param  array<string, string>  $config
     * @return string
     */
    private function generateCssVariables(array $config): string
    {
        $css = ":root {\n";

        foreach ($config as $key => $value) {
            $cssVar = '--'.str_replace('_', '-', $key);
            $css .= "  {$cssVar}: {$value};\n";
        }

        $css .= "}\n";

        return $css;
    }

    /**
     * Find organization by ID or slug (with caching)
     *
     * @param  string  $identifier
     * @return Organization|null
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
     *
     * @param  Organization  $org
     * @return bool
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
     *
     * @return Response
     */
    private function unauthorizedResponse(): Response
    {
        return $this->errorResponse('Unauthorized: Branding access requires authentication', 403);
    }

    /**
     * Generate consistent error response
     *
     * @param  string  $message
     * @param  int  $status
     * @param  string|null  $fallbackCss
     * @return Response
     */
    private function errorResponse(string $message, int $status, ?string $fallbackCss = null): Response
    {
        $css = $fallbackCss ?? sprintf(
            "/* Coolify Branding Error: %s (HTTP %d) */\n:root { --error: true; }",
            $message,
            $status
        );

        return response($css, $status)
            ->header('Content-Type', 'text/css; charset=UTF-8')
            ->header('X-Branding-Error', strtolower(str_replace(' ', '-', $message)))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Get cache key for organization CSS
     *
     * @param  string  $organizationSlug
     * @param  int  $updatedTimestamp
     * @return string
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
     *
     * @param  array<string, string>  $themeVariables
     * @param  string|null  $customCss
     * @return string
     */
    private function generateEtag(array $themeVariables, ?string $customCss = null): string
    {
        $content = json_encode($themeVariables).($customCss ?? '');
        $hash = md5($content);

        return '"'.$hash.'"';
    }

    /**
     * Get Cache-Control header value
     *
     * @return string
     */
    private function getCacheControlHeader(): string
    {
        $ttl = config('enterprise.white_label.cache_ttl', 3600);

        return "public, max-age={$ttl}";
    }

    /**
     * Get default CSS as fallback
     *
     * @return string
     */
    private function getDefaultCss(): string
    {
        $defaults = config('enterprise.white_label.default_theme', []);

        return $this->generateCssVariables($defaults);
    }

    /**
     * Minify CSS for production
     *
     * @param  string  $css
     * @return string
     */
    private function minifyCss(string $css): string
    {
        // Remove comments (preserving license comments)
        $css = preg_replace('!/\*(?![!*])(.*?)\*/!s', '', $css);

        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);

        return trim($css);
    }
}
