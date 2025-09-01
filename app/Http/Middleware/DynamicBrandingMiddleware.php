<?php

namespace App\Http\Middleware;

use App\Models\WhiteLabelConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class DynamicBrandingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware demonstrates how single-instance multi-domain branding works.
     * The same Coolify instance serves different branding based on the request domain.
     */
    public function handle(Request $request, Closure $next)
    {
        $domain = $request->getHost();

        // Find branding configuration for this domain
        $branding = WhiteLabelConfig::findByDomain($domain);

        if ($branding) {
            // Set branding context for the entire request lifecycle
            app()->instance('current.branding', $branding);
            app()->instance('current.organization', $branding->organization);

            // Share branding data with all views
            View::share([
                'branding' => $branding,
                'platformName' => $branding->getPlatformName(),
                'customLogo' => $branding->getLogoUrl(),
                'hideDefaultBranding' => $branding->shouldHideCoolifyBranding(),
                'themeVariables' => $branding->getThemeVariables(),
            ]);

            // Add branding to request attributes for controllers
            $request->attributes->set('branding', $branding);
            $request->attributes->set('organization', $branding->organization);

            // Log domain-based branding for debugging
            if (config('app.debug')) {
                logger()->info('Domain-based branding applied', [
                    'domain' => $domain,
                    'platform_name' => $branding->getPlatformName(),
                    'organization_id' => $branding->organization_id,
                ]);
            }
        } else {
            // No custom branding found - use default Coolify branding
            View::share([
                'branding' => null,
                'platformName' => 'Coolify',
                'customLogo' => null,
                'hideDefaultBranding' => false,
                'themeVariables' => WhiteLabelConfig::createDefault('')->getDefaultThemeVariables(),
            ]);

            if (config('app.debug')) {
                logger()->info('Default branding applied', [
                    'domain' => $domain,
                    'reason' => 'No custom branding configuration found',
                ]);
            }
        }

        return $next($request);
    }
}
