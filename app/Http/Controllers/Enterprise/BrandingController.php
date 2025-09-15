<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use App\Services\Enterprise\WhiteLabelService;
use App\Services\Enterprise\BrandingCacheService;
use App\Services\Enterprise\DomainValidationService;
use App\Services\Enterprise\EmailTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    protected WhiteLabelService $whiteLabelService;
    protected BrandingCacheService $cacheService;
    protected DomainValidationService $domainService;
    protected EmailTemplateService $emailService;

    public function __construct(
        WhiteLabelService $whiteLabelService,
        BrandingCacheService $cacheService,
        DomainValidationService $domainService,
        EmailTemplateService $emailService
    ) {
        $this->whiteLabelService = $whiteLabelService;
        $this->cacheService = $cacheService;
        $this->domainService = $domainService;
        $this->emailService = $emailService;
    }

    /**
     * Display branding management dashboard
     */
    public function index(Request $request): Response
    {
        $organization = $this->getCurrentOrganization($request);

        Gate::authorize('manage-branding', $organization);

        $config = $this->whiteLabelService->getOrCreateConfig($organization);
        $cacheStats = $this->cacheService->getCacheStats($organization->id);

        return Inertia::render('Enterprise/WhiteLabel/BrandingManager', [
            'organization' => $organization,
            'config' => [
                'id' => $config->id,
                'platform_name' => $config->platform_name,
                'logo_url' => $config->logo_url,
                'theme_config' => $config->theme_config,
                'custom_domains' => $config->custom_domains,
                'hide_coolify_branding' => $config->hide_coolify_branding,
                'custom_css' => $config->custom_css,
            ],
            'themeVariables' => $config->getThemeVariables(),
            'emailTemplates' => $config->getAvailableEmailTemplates(),
            'cacheStats' => $cacheStats,
        ]);
    }

    /**
     * Update branding configuration
     */
    public function update(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $validated = $request->validate([
            'platform_name' => 'required|string|max:255',
            'hide_coolify_branding' => 'boolean',
            'custom_css' => 'nullable|string|max:50000',
        ]);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
        $config->update($validated);

        // Clear cache
        $this->cacheService->clearOrganizationCache($organization->id);

        return back()->with('success', 'Branding configuration updated successfully');
    }

    /**
     * Upload and process logo
     */
    public function uploadLogo(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $request->validate([
            'logo' => 'required|image|max:5120', // 5MB max
        ]);

        try {
            $logoUrl = $this->whiteLabelService->processLogo($request->file('logo'), $organization);

            $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
            $config->update(['logo_url' => $logoUrl]);

            return response()->json([
                'success' => true,
                'logo_url' => $logoUrl,
                'message' => 'Logo uploaded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update theme configuration
     */
    public function updateTheme(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $validated = $request->validate([
            'theme_config' => 'required|array',
            'theme_config.primary_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.secondary_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.accent_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.background_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.text_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.sidebar_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.border_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.success_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.warning_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.error_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.info_color' => 'required|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'theme_config.enable_dark_mode' => 'boolean',
        ]);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
        $config->update($validated);

        // Compile and cache new theme
        $compiledCss = $this->whiteLabelService->compileTheme($config);

        return response()->json([
            'success' => true,
            'compiled_css' => $compiledCss,
            'message' => 'Theme updated successfully',
        ]);
    }

    /**
     * Preview theme changes
     */
    public function previewTheme(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();

        // Create temporary config with preview changes
        $tempConfig = clone $config;
        $tempConfig->theme_config = $request->input('theme_config', $config->theme_config);
        $tempConfig->custom_css = $request->input('custom_css', $config->custom_css);

        $compiledCss = $this->whiteLabelService->compileTheme($tempConfig);

        return response()->json([
            'success' => true,
            'compiled_css' => $compiledCss,
        ]);
    }

    /**
     * Manage custom domains
     */
    public function domains(Request $request, string $organizationId): Response
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();

        return Inertia::render('Enterprise/WhiteLabel/DomainManager', [
            'organization' => $organization,
            'domains' => $config->custom_domains ?? [],
            'verification_instructions' => $this->getVerificationInstructions($organization),
        ]);
    }

    /**
     * Add custom domain
     */
    public function addDomain(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();

        // Validate domain
        $validation = $this->domainService->performComprehensiveValidation(
            $validated['domain'],
            $organization->id
        );

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'validation' => $validation,
            ], 422);
        }

        // Add domain
        $result = $this->whiteLabelService->setCustomDomain($config, $validated['domain']);

        return response()->json($result);
    }

    /**
     * Validate domain
     */
    public function validateDomain(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $validation = $this->domainService->performComprehensiveValidation(
            $validated['domain'],
            $organization->id
        );

        return response()->json($validation);
    }

    /**
     * Remove custom domain
     */
    public function removeDomain(Request $request, string $organizationId, string $domain)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
        $config->removeCustomDomain($domain);
        $config->save();

        // Clear domain cache
        $this->cacheService->clearDomainCache($domain);

        return response()->json([
            'success' => true,
            'message' => 'Domain removed successfully',
        ]);
    }

    /**
     * Email template management
     */
    public function emailTemplates(Request $request, string $organizationId): Response
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();

        return Inertia::render('Enterprise/WhiteLabel/EmailTemplateEditor', [
            'organization' => $organization,
            'availableTemplates' => $config->getAvailableEmailTemplates(),
            'customTemplates' => $config->custom_email_templates ?? [],
        ]);
    }

    /**
     * Update email template
     */
    public function updateEmailTemplate(Request $request, string $organizationId, string $templateName)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string|max:100000',
        ]);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
        $config->setEmailTemplate($templateName, $validated);
        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Email template updated successfully',
        ]);
    }

    /**
     * Preview email template
     */
    public function previewEmailTemplate(Request $request, string $organizationId, string $templateName)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();

        $preview = $this->emailService->previewTemplate(
            $config,
            $templateName,
            $request->input('sample_data', [])
        );

        return response()->json($preview);
    }

    /**
     * Reset email template to default
     */
    public function resetEmailTemplate(Request $request, string $organizationId, string $templateName)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();

        $templates = $config->custom_email_templates ?? [];
        unset($templates[$templateName]);

        $config->custom_email_templates = $templates;
        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Email template reset to default',
        ]);
    }

    /**
     * Export branding configuration
     */
    public function export(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
        $exportData = $this->whiteLabelService->exportConfiguration($config);

        return response()->json($exportData)
            ->header('Content-Disposition', 'attachment; filename="branding-config-' . $organization->id . '.json"');
    }

    /**
     * Import branding configuration
     */
    public function import(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $request->validate([
            'config_file' => 'required|file|mimes:json|max:1024', // 1MB max
        ]);

        try {
            $data = json_decode($request->file('config_file')->get(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON file');
            }

            $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
            $this->whiteLabelService->importConfiguration($config, $data);

            return response()->json([
                'success' => true,
                'message' => 'Branding configuration imported successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reset branding to defaults
     */
    public function reset(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $config = WhiteLabelConfig::where('organization_id', $organization->id)->firstOrFail();
        $config->resetToDefaults();

        // Clear all caches
        $this->cacheService->clearOrganizationCache($organization->id);

        return response()->json([
            'success' => true,
            'message' => 'Branding reset to defaults',
        ]);
    }

    /**
     * Get cache statistics
     */
    public function cacheStats(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $stats = $this->cacheService->getCacheStats($organization->id);

        return response()->json($stats);
    }

    /**
     * Clear branding cache
     */
    public function clearCache(Request $request, string $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('manage-branding', $organization);

        $this->cacheService->clearOrganizationCache($organization->id);

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully',
        ]);
    }

    /**
     * Get current organization from request
     */
    protected function getCurrentOrganization(Request $request): Organization
    {
        // This would typically come from session or auth context
        $organizationId = $request->route('organization') ??
                         $request->session()->get('current_organization_id') ??
                         $request->user()->organizations()->first()?->id;

        return Organization::findOrFail($organizationId);
    }

    /**
     * Get domain verification instructions
     */
    protected function getVerificationInstructions(Organization $organization): array
    {
        $token = $this->domainService->generateVerificationToken('example.com', $organization->id);

        return [
            'dns_txt' => [
                'type' => 'TXT',
                'name' => '@',
                'value' => "coolify-verify={$token}",
                'ttl' => 3600,
            ],
            'dns_a' => [
                'type' => 'A',
                'name' => '@',
                'value' => config('whitelabel.server_ips.0', 'YOUR_SERVER_IP'),
                'ttl' => 3600,
            ],
            'ssl' => [
                'message' => 'Ensure your domain has a valid SSL certificate',
                'providers' => ['Let\'s Encrypt (free)', 'Cloudflare', 'Your hosting provider'],
            ],
        ];
    }
}