<?php

namespace App\Services\Enterprise;

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class WhiteLabelService
{
    protected BrandingCacheService $cacheService;
    protected DomainValidationService $domainService;
    protected EmailTemplateService $emailService;

    public function __construct(
        BrandingCacheService $cacheService,
        DomainValidationService $domainService,
        EmailTemplateService $emailService
    ) {
        $this->cacheService = $cacheService;
        $this->domainService = $domainService;
        $this->emailService = $emailService;
    }

    /**
     * Get or create white label config for organization
     */
    public function getOrCreateConfig(Organization $organization): WhiteLabelConfig
    {
        return WhiteLabelConfig::firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'platform_name' => $organization->name,
                'theme_config' => [],
                'custom_domains' => [],
                'hide_coolify_branding' => false,
                'custom_email_templates' => [],
            ]
        );
    }

    /**
     * Process and upload logo with validation and optimization
     */
    public function processLogo(UploadedFile $file, Organization $organization): string
    {
        // Validate image file
        $this->validateLogoFile($file);

        // Generate unique filename
        $filename = $this->generateLogoFilename($organization, $file);

        // Process and optimize image
        $image = Image::read($file);

        // Resize to maximum dimensions while maintaining aspect ratio
        $image->scaleDown(width: 500, height: 200);

        // Store original logo
        $path = "branding/logos/{$organization->id}/{$filename}";
        Storage::disk('public')->put($path, (string) $image->encode());

        // Generate favicon versions
        $this->generateFavicons($image, $organization);

        // Generate SVG version if applicable
        if ($file->getClientOriginalExtension() !== 'svg') {
            $this->generateSvgVersion($image, $organization);
        }

        // Clear cache for this organization
        $this->cacheService->clearOrganizationCache($organization->id);

        return Storage::url($path);
    }

    /**
     * Validate logo file
     */
    protected function validateLogoFile(UploadedFile $file): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('Invalid file type. Allowed types: JPG, PNG, GIF, SVG, WebP');
        }

        // Maximum file size: 5MB
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('File size exceeds 5MB limit');
        }
    }

    /**
     * Generate unique logo filename
     */
    protected function generateLogoFilename(Organization $organization, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        $hash = substr(md5($organization->id . $timestamp), 0, 8);

        return "logo_{$timestamp}_{$hash}.{$extension}";
    }

    /**
     * Generate favicon versions from logo
     */
    protected function generateFavicons($image, Organization $organization): void
    {
        $sizes = [16, 32, 64, 128, 192];

        foreach ($sizes as $size) {
            $favicon = clone $image;
            $favicon->cover($size, $size);

            $path = "branding/favicons/{$organization->id}/favicon-{$size}x{$size}.png";
            Storage::disk('public')->put($path, (string) $favicon->toPng());
        }

        // Generate ICO file with multiple sizes
        $this->generateIcoFile($organization);
    }

    /**
     * Generate ICO file with multiple sizes
     */
    protected function generateIcoFile(Organization $organization): void
    {
        // This would require a specialized ICO library
        // For now, we'll use the 32x32 PNG as a fallback
        $source = Storage::disk('public')->get("branding/favicons/{$organization->id}/favicon-32x32.png");
        Storage::disk('public')->put("branding/favicons/{$organization->id}/favicon.ico", $source);
    }

    /**
     * Generate SVG version of logo for theming
     */
    protected function generateSvgVersion($image, Organization $organization): void
    {
        // This would require image tracing library
        // Placeholder for SVG generation logic
        $path = "branding/logos/{$organization->id}/logo.svg";
        // Storage::disk('public')->put($path, $svgContent);
    }

    /**
     * Compile theme with SASS preprocessing
     */
    public function compileTheme(WhiteLabelConfig $config): string
    {
        // Get theme variables
        $variables = $config->getThemeVariables();

        // Start with CSS variables
        $css = $this->generateCssVariables($variables);

        // Add component-specific styles
        $css .= $this->generateComponentStyles($variables);

        // Add dark mode styles if configured
        if ($config->getThemeVariable('enable_dark_mode', false)) {
            $css .= $this->generateDarkModeStyles($variables);
        }

        // Add custom CSS if provided
        if ($config->custom_css) {
            $css .= "\n/* Custom CSS */\n" . $config->custom_css;
        }

        // Minify CSS in production
        if (app()->environment('production')) {
            $css = $this->minifyCss($css);
        }

        // Cache compiled theme
        $this->cacheService->cacheCompiledTheme($config->organization_id, $css);

        return $css;
    }

    /**
     * Generate CSS variables from theme config
     */
    protected function generateCssVariables(array $variables): string
    {
        $css = ":root {\n";

        foreach ($variables as $key => $value) {
            $cssVar = '--' . str_replace('_', '-', $key);
            $css .= "  {$cssVar}: {$value};\n";

            // Generate RGB versions for opacity support
            if ($this->isHexColor($value)) {
                $rgb = $this->hexToRgb($value);
                $css .= "  {$cssVar}-rgb: {$rgb};\n";
            }
        }

        // Add derived colors
        $css .= $this->generateDerivedColors($variables);

        $css .= "}\n";

        return $css;
    }

    /**
     * Generate component-specific styles
     */
    protected function generateComponentStyles(array $variables): string
    {
        $css = "\n/* Component Styles */\n";

        // Button styles
        $css .= ".btn-primary {\n";
        $css .= "  background-color: var(--primary-color);\n";
        $css .= "  border-color: var(--primary-color);\n";
        $css .= "}\n";

        $css .= ".btn-primary:hover {\n";
        $css .= "  background-color: var(--primary-color-dark);\n";
        $css .= "  border-color: var(--primary-color-dark);\n";
        $css .= "}\n";

        // Navigation styles
        $css .= ".navbar {\n";
        $css .= "  background-color: var(--sidebar-color);\n";
        $css .= "  border-color: var(--border-color);\n";
        $css .= "}\n";

        // Add more component styles as needed

        return $css;
    }

    /**
     * Generate dark mode styles
     */
    protected function generateDarkModeStyles(array $variables): string
    {
        $css = "\n/* Dark Mode */\n";
        $css .= "@media (prefers-color-scheme: dark) {\n";
        $css .= "  :root {\n";

        // Invert or adjust colors for dark mode
        $darkVariables = $this->generateDarkModeVariables($variables);
        foreach ($darkVariables as $key => $value) {
            $cssVar = '--' . str_replace('_', '-', $key);
            $css .= "    {$cssVar}: {$value};\n";
        }

        $css .= "  }\n";
        $css .= "}\n";

        $css .= ".dark {\n";
        foreach ($darkVariables as $key => $value) {
            $cssVar = '--' . str_replace('_', '-', $key);
            $css .= "  {$cssVar}: {$value};\n";
        }
        $css .= "}\n";

        return $css;
    }

    /**
     * Generate dark mode color variables
     */
    protected function generateDarkModeVariables(array $variables): array
    {
        $darkVariables = [];

        // Invert background and text colors
        $darkVariables['background_color'] = '#1a1a1a';
        $darkVariables['text_color'] = '#f0f0f0';
        $darkVariables['sidebar_color'] = '#2a2a2a';
        $darkVariables['border_color'] = '#3a3a3a';

        // Keep accent colors but adjust brightness
        foreach (['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info'] as $colorName) {
            $key = $colorName . '_color';
            if (isset($variables[$key])) {
                $darkVariables[$key] = $this->adjustColorBrightness($variables[$key], 20);
            }
        }

        return $darkVariables;
    }

    /**
     * Generate derived colors (hover, focus, disabled states)
     */
    protected function generateDerivedColors(array $variables): string
    {
        $css = "  /* Derived Colors */\n";

        foreach (['primary', 'secondary', 'accent'] as $colorName) {
            $key = $colorName . '_color';
            if (isset($variables[$key])) {
                $baseColor = $variables[$key];

                // Generate lighter and darker variants
                $css .= "  --{$colorName}-color-light: " . $this->adjustColorBrightness($baseColor, 20) . ";\n";
                $css .= "  --{$colorName}-color-dark: " . $this->adjustColorBrightness($baseColor, -20) . ";\n";
                $css .= "  --{$colorName}-color-alpha: " . $this->addAlphaToColor($baseColor, 0.1) . ";\n";
            }
        }

        return $css;
    }

    /**
     * Minify CSS for production
     */
    protected function minifyCss(string $css): string
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace([' {', '{ ', ' }', '} ', ': ', ' ;'], ['{', '{', '}', '}', ':', ';'], $css);

        return trim($css);
    }

    /**
     * Validate and set custom domain
     */
    public function setCustomDomain(WhiteLabelConfig $config, string $domain): array
    {
        // Validate domain format
        if (!$config->isValidDomain($domain)) {
            throw new \InvalidArgumentException('Invalid domain format');
        }

        // Check DNS configuration
        $dnsValidation = $this->domainService->validateDns($domain);
        if (!$dnsValidation['valid']) {
            return [
                'success' => false,
                'message' => 'DNS validation failed',
                'details' => $dnsValidation,
            ];
        }

        // Check SSL certificate
        $sslValidation = $this->domainService->validateSsl($domain);
        if (!$sslValidation['valid'] && app()->environment('production')) {
            return [
                'success' => false,
                'message' => 'SSL validation failed',
                'details' => $sslValidation,
            ];
        }

        // Add domain to config
        $config->addCustomDomain($domain);
        $config->save();

        // Clear cache for domain-based branding
        $this->cacheService->clearDomainCache($domain);

        return [
            'success' => true,
            'message' => 'Domain configured successfully',
            'dns' => $dnsValidation,
            'ssl' => $sslValidation,
        ];
    }

    /**
     * Generate email template with branding
     */
    public function generateEmailTemplate(WhiteLabelConfig $config, string $templateName, array $data = []): string
    {
        return $this->emailService->generateTemplate($config, $templateName, $data);
    }

    /**
     * Export branding configuration
     */
    public function exportConfiguration(WhiteLabelConfig $config): array
    {
        return [
            'platform_name' => $config->platform_name,
            'theme_config' => $config->theme_config,
            'custom_css' => $config->custom_css,
            'email_templates' => $config->custom_email_templates,
            'hide_coolify_branding' => $config->hide_coolify_branding,
            'exported_at' => now()->toIso8601String(),
            'version' => '1.0',
        ];
    }

    /**
     * Import branding configuration
     */
    public function importConfiguration(WhiteLabelConfig $config, array $data): void
    {
        // Validate import data
        $this->validateImportData($data);

        // Import configuration
        $config->update([
            'platform_name' => $data['platform_name'] ?? $config->platform_name,
            'theme_config' => $data['theme_config'] ?? $config->theme_config,
            'custom_css' => $data['custom_css'] ?? $config->custom_css,
            'custom_email_templates' => $data['email_templates'] ?? $config->custom_email_templates,
            'hide_coolify_branding' => $data['hide_coolify_branding'] ?? $config->hide_coolify_branding,
        ]);

        // Clear cache
        $this->cacheService->clearOrganizationCache($config->organization_id);
    }

    /**
     * Validate import data structure
     */
    protected function validateImportData(array $data): void
    {
        if (!isset($data['version'])) {
            throw new \InvalidArgumentException('Invalid import file: missing version');
        }

        if (!isset($data['exported_at'])) {
            throw new \InvalidArgumentException('Invalid import file: missing export timestamp');
        }
    }

    /**
     * Helper: Check if string is hex color
     */
    protected function isHexColor(string $color): bool
    {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color) === 1;
    }

    /**
     * Helper: Convert hex to RGB
     */
    protected function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r}, {$g}, {$b}";
    }

    /**
     * Helper: Adjust color brightness
     */
    protected function adjustColorBrightness(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));

        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                  . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                  . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Helper: Add alpha channel to color
     */
    protected function addAlphaToColor(string $hex, float $alpha): string
    {
        $rgb = $this->hexToRgb($hex);
        return "rgba({$rgb}, {$alpha})";
    }
}