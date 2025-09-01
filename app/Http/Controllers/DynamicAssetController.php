<?php

namespace App\Http\Controllers;

use App\Models\WhiteLabelConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class DynamicAssetController extends Controller
{
    /**
     * Generate dynamic CSS based on the requesting domain.
     *
     * This demonstrates how the same endpoint serves different CSS
     * based on the domain making the request.
     */
    public function dynamicCss(Request $request): Response
    {
        $domain = $request->getHost();
        $cacheKey = "dynamic_css:{$domain}";

        // Cache the generated CSS for performance
        $css = Cache::remember($cacheKey, 3600, function () use ($domain) {
            return $this->generateCssForDomain($domain);
        });

        return response($css, 200, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'public, max-age=3600',
            'X-Generated-For-Domain' => $domain, // Debug header
        ]);
    }

    /**
     * Generate CSS content for a specific domain.
     */
    private function generateCssForDomain(string $domain): string
    {
        // Find branding config for this domain
        $branding = WhiteLabelConfig::findByDomain($domain);

        if (! $branding) {
            return $this->getDefaultCss();
        }

        // Start with base CSS
        $css = $this->getBaseCss();

        // Add custom CSS variables
        $css .= "\n\n/* Custom theme for {$domain} */\n";
        $css .= $branding->generateCssVariables();

        // Add any custom CSS
        if ($branding->custom_css) {
            $css .= "\n\n/* Custom CSS for {$domain} */\n";
            $css .= $branding->custom_css;
        }

        return $css;
    }

    /**
     * Get the base CSS that's common to all themes.
     */
    private function getBaseCss(): string
    {
        $baseCssPath = resource_path('css/base-theme.css');

        if (file_exists($baseCssPath)) {
            return file_get_contents($baseCssPath);
        }

        // Fallback base CSS if file doesn't exist
        return $this->getFallbackBaseCss();
    }

    /**
     * Get default Coolify CSS.
     */
    private function getDefaultCss(): string
    {
        $defaultCssPath = public_path('css/app.css');

        if (file_exists($defaultCssPath)) {
            return file_get_contents($defaultCssPath);
        }

        return $this->getFallbackBaseCss();
    }

    /**
     * Fallback CSS if no files are found.
     */
    private function getFallbackBaseCss(): string
    {
        return <<<'CSS'
/* Fallback Base CSS for Dynamic Branding Demo */
:root {
  --primary-color: #3b82f6;
  --secondary-color: #1f2937;
  --accent-color: #10b981;
  --background-color: #ffffff;
  --text-color: #1f2937;
  --border-color: #e5e7eb;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background-color: var(--background-color);
  color: var(--text-color);
  margin: 0;
  padding: 0;
}

.navbar {
  background-color: var(--primary-color);
  color: white;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.navbar img {
  height: 40px;
}

.platform-name {
  font-size: 1.5rem;
  font-weight: bold;
}

.btn-primary {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  text-decoration: none;
  display: inline-block;
}

.btn-primary:hover {
  opacity: 0.9;
}

.card {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.text-primary {
  color: var(--primary-color);
}

.text-secondary {
  color: var(--secondary-color);
}

.bg-primary {
  background-color: var(--primary-color);
}

.border-primary {
  border-color: var(--primary-color);
}
CSS;
    }

    /**
     * Serve dynamic favicon based on domain branding.
     */
    public function dynamicFavicon(Request $request): Response
    {
        $domain = $request->getHost();
        $branding = WhiteLabelConfig::findByDomain($domain);

        if ($branding && $branding->getLogoUrl()) {
            // Redirect to custom logo
            return redirect($branding->getLogoUrl());
        }

        // Serve default favicon
        $defaultFavicon = public_path('favicon.ico');
        if (file_exists($defaultFavicon)) {
            return response(file_get_contents($defaultFavicon), 200, [
                'Content-Type' => 'image/x-icon',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response('', 404);
    }

    /**
     * Debug endpoint to show how domain detection works.
     */
    public function debugBranding(Request $request): array
    {
        $domain = $request->getHost();
        $branding = WhiteLabelConfig::findByDomain($domain);

        return [
            'domain' => $domain,
            'has_custom_branding' => $branding !== null,
            'platform_name' => $branding?->getPlatformName() ?? 'Coolify (Default)',
            'custom_logo' => $branding?->getLogoUrl(),
            'theme_variables' => $branding?->getThemeVariables() ?? WhiteLabelConfig::createDefault('')->getDefaultThemeVariables(),
            'custom_domains' => $branding?->getCustomDomains() ?? [],
            'hide_coolify_branding' => $branding?->shouldHideCoolifyBranding() ?? false,
            'organization_id' => $branding?->organization_id,
            'request_headers' => [
                'host' => $request->header('host'),
                'user_agent' => $request->header('user-agent'),
                'x_forwarded_host' => $request->header('x-forwarded-host'),
            ],
        ];
    }
}
