<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhiteLabelConfig extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'platform_name',
        'logo_url',
        'theme_config',
        'custom_domains',
        'hide_coolify_branding',
        'custom_email_templates',
        'custom_css',
    ];

    protected $casts = [
        'theme_config' => 'array',
        'custom_domains' => 'array',
        'custom_email_templates' => 'array',
        'hide_coolify_branding' => 'boolean',
    ];

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Theme Configuration Methods
    public function getThemeVariable(string $variable, $default = null)
    {
        return $this->theme_config[$variable] ?? $default;
    }

    public function setThemeVariable(string $variable, $value): void
    {
        $config = $this->theme_config ?? [];
        $config[$variable] = $value;
        $this->theme_config = $config;
    }

    public function getThemeVariables(): array
    {
        $defaults = $this->getDefaultThemeVariables();

        return array_merge($defaults, $this->theme_config ?? []);
    }

    public function getDefaultThemeVariables(): array
    {
        return [
            'primary_color' => '#3b82f6',
            'secondary_color' => '#1f2937',
            'accent_color' => '#10b981',
            'background_color' => '#ffffff',
            'text_color' => '#1f2937',
            'sidebar_color' => '#f9fafb',
            'border_color' => '#e5e7eb',
            'success_color' => '#10b981',
            'warning_color' => '#f59e0b',
            'error_color' => '#ef4444',
            'info_color' => '#3b82f6',
        ];
    }

    public function generateCssVariables(): string
    {
        $variables = $this->getThemeVariables();
        $css = ':root {'.PHP_EOL;

        foreach ($variables as $key => $value) {
            $cssVar = '--'.str_replace('_', '-', $key);
            $css .= "  {$cssVar}: {$value};".PHP_EOL;
        }

        $css .= '}'.PHP_EOL;

        if ($this->custom_css) {
            $css .= PHP_EOL.$this->custom_css;
        }

        return $css;
    }

    // Domain Management Methods
    public function addCustomDomain(string $domain): void
    {
        $domains = $this->custom_domains ?? [];
        if (! in_array($domain, $domains)) {
            $domains[] = $domain;
            $this->custom_domains = $domains;
        }
    }

    public function removeCustomDomain(string $domain): void
    {
        $domains = $this->custom_domains ?? [];
        $this->custom_domains = array_values(array_filter($domains, fn ($d) => $d !== $domain));
    }

    public function hasCustomDomain(string $domain): bool
    {
        return in_array($domain, $this->custom_domains ?? []);
    }

    public function getCustomDomains(): array
    {
        return $this->custom_domains ?? [];
    }

    // Email Template Methods
    public function getEmailTemplate(string $templateName): ?array
    {
        return $this->custom_email_templates[$templateName] ?? null;
    }

    public function setEmailTemplate(string $templateName, array $template): void
    {
        $templates = $this->custom_email_templates ?? [];
        $templates[$templateName] = $template;
        $this->custom_email_templates = $templates;
    }

    public function hasCustomEmailTemplate(string $templateName): bool
    {
        return isset($this->custom_email_templates[$templateName]);
    }

    public function getAvailableEmailTemplates(): array
    {
        return [
            'welcome' => 'Welcome Email',
            'password_reset' => 'Password Reset',
            'email_verification' => 'Email Verification',
            'invitation' => 'Team Invitation',
            'deployment_success' => 'Deployment Success',
            'deployment_failure' => 'Deployment Failure',
            'server_unreachable' => 'Server Unreachable',
            'backup_success' => 'Backup Success',
            'backup_failure' => 'Backup Failure',
        ];
    }

    // Branding Methods
    public function getPlatformName(): string
    {
        return $this->platform_name ?: 'Coolify';
    }

    public function getLogoUrl(): ?string
    {
        return $this->logo_url;
    }

    public function hasCustomLogo(): bool
    {
        return ! empty($this->logo_url);
    }

    public function shouldHideCoolifyBranding(): bool
    {
        return $this->hide_coolify_branding;
    }

    // Validation Methods
    public function isValidThemeColor(string $color): bool
    {
        // Check if it's a valid hex color
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color) === 1;
    }

    public function isValidDomain(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    public function isValidLogoUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if it's an image URL (basic check)
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, $imageExtensions);
    }

    // Factory Methods
    public static function createDefault(string $organizationId): self
    {
        return self::create([
            'organization_id' => $organizationId,
            'platform_name' => 'Coolify',
            'theme_config' => [],
            'custom_domains' => [],
            'hide_coolify_branding' => false,
            'custom_email_templates' => [],
        ]);
    }

    public function resetToDefaults(): void
    {
        $this->update([
            'platform_name' => 'Coolify',
            'logo_url' => null,
            'theme_config' => [],
            'custom_domains' => [],
            'hide_coolify_branding' => false,
            'custom_email_templates' => [],
            'custom_css' => null,
        ]);
    }

    // Domain Detection for Multi-Tenant Branding
    public static function findByDomain(string $domain): ?self
    {
        return self::whereJsonContains('custom_domains', $domain)->first();
    }

    public static function findByOrganization(string $organizationId): ?self
    {
        return self::where('organization_id', $organizationId)->first();
    }
}
