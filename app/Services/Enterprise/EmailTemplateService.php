<?php

namespace App\Services\Enterprise;

use App\Models\WhiteLabelConfig;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class EmailTemplateService
{
    protected CssToInlineStyles $cssInliner;
    protected array $defaultVariables = [];

    public function __construct()
    {
        $this->cssInliner = new CssToInlineStyles();
        $this->setDefaultVariables();
    }

    /**
     * Set default template variables
     */
    protected function setDefaultVariables(): void
    {
        $this->defaultVariables = [
            'app_name' => config('app.name', 'Coolify'),
            'app_url' => config('app.url'),
            'support_email' => config('mail.from.address'),
            'current_year' => date('Y'),
            'logo_url' => asset('images/logo.png'),
        ];
    }

    /**
     * Generate email template with branding
     */
    public function generateTemplate(WhiteLabelConfig $config, string $templateName, array $data = []): string
    {
        // Merge branding variables with template data
        $variables = $this->prepareBrandingVariables($config, $data);

        // Get template content
        $template = $this->getTemplate($config, $templateName);

        // Process template with variables
        $html = $this->processTemplate($template, $variables);

        // Apply branding styles
        $html = $this->applyBrandingStyles($html, $config);

        // Inline CSS for email compatibility
        $html = $this->inlineCss($html, $config);

        return $html;
    }

    /**
     * Prepare branding variables for template
     */
    protected function prepareBrandingVariables(WhiteLabelConfig $config, array $data): array
    {
        $brandingVars = [
            'platform_name' => $config->getPlatformName(),
            'logo_url' => $config->getLogoUrl() ?: $this->defaultVariables['logo_url'],
            'primary_color' => $config->getThemeVariable('primary_color', '#3b82f6'),
            'secondary_color' => $config->getThemeVariable('secondary_color', '#1f2937'),
            'accent_color' => $config->getThemeVariable('accent_color', '#10b981'),
            'text_color' => $config->getThemeVariable('text_color', '#1f2937'),
            'background_color' => $config->getThemeVariable('background_color', '#ffffff'),
            'hide_branding' => $config->shouldHideCoolifyBranding(),
        ];

        return array_merge($this->defaultVariables, $brandingVars, $data);
    }

    /**
     * Get template content
     */
    protected function getTemplate(WhiteLabelConfig $config, string $templateName): string
    {
        // Check for custom template
        if ($config->hasCustomEmailTemplate($templateName)) {
            $customTemplate = $config->getEmailTemplate($templateName);
            return $customTemplate['content'] ?? $this->getDefaultTemplate($templateName);
        }

        return $this->getDefaultTemplate($templateName);
    }

    /**
     * Get default template
     */
    protected function getDefaultTemplate(string $templateName): string
    {
        $templates = [
            'welcome' => $this->getWelcomeTemplate(),
            'password_reset' => $this->getPasswordResetTemplate(),
            'email_verification' => $this->getEmailVerificationTemplate(),
            'invitation' => $this->getInvitationTemplate(),
            'deployment_success' => $this->getDeploymentSuccessTemplate(),
            'deployment_failure' => $this->getDeploymentFailureTemplate(),
            'server_unreachable' => $this->getServerUnreachableTemplate(),
            'backup_success' => $this->getBackupSuccessTemplate(),
            'backup_failure' => $this->getBackupFailureTemplate(),
        ];

        return $templates[$templateName] ?? $this->getGenericTemplate();
    }

    /**
     * Process template with variables
     */
    protected function processTemplate(string $template, array $variables): string
    {
        // Replace variables in template
        foreach ($variables as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace(
                    ['{{' . $key . '}}', '{{ ' . $key . ' }}'],
                    $value,
                    $template
                );
            }
        }

        // Process conditionals
        $template = $this->processConditionals($template, $variables);

        // Process loops
        $template = $this->processLoops($template, $variables);

        return $template;
    }

    /**
     * Process conditional statements in template
     */
    protected function processConditionals(string $template, array $variables): string
    {
        // Process @if statements
        $pattern = '/@if\s*\((.*?)\)(.*?)@endif/s';
        $template = preg_replace_callback($pattern, function ($matches) use ($variables) {
            $condition = $matches[1];
            $content = $matches[2];

            // Simple variable check
            if (isset($variables[$condition]) && $variables[$condition]) {
                return $content;
            }

            return '';
        }, $template);

        // Process @unless statements
        $pattern = '/@unless\s*\((.*?)\)(.*?)@endunless/s';
        $template = preg_replace_callback($pattern, function ($matches) use ($variables) {
            $condition = $matches[1];
            $content = $matches[2];

            if (!isset($variables[$condition]) || !$variables[$condition]) {
                return $content;
            }

            return '';
        }, $template);

        return $template;
    }

    /**
     * Process loops in template
     */
    protected function processLoops(string $template, array $variables): string
    {
        // Process @foreach loops
        $pattern = '/@foreach\s*\((.*?)\s+as\s+(.*?)\)(.*?)@endforeach/s';
        $template = preg_replace_callback($pattern, function ($matches) use ($variables) {
            $arrayName = trim($matches[1]);
            $itemName = trim($matches[2]);
            $content = $matches[3];

            if (!isset($variables[$arrayName]) || !is_array($variables[$arrayName])) {
                return '';
            }

            $output = '';
            foreach ($variables[$arrayName] as $item) {
                $itemContent = $content;
                if (is_array($item)) {
                    foreach ($item as $key => $value) {
                        if (is_string($value) || is_numeric($value)) {
                            $itemContent = str_replace(
                                ['{{' . $itemName . '.' . $key . '}}', '{{ ' . $itemName . '.' . $key . ' }}'],
                                $value,
                                $itemContent
                            );
                        }
                    }
                } else {
                    $itemContent = str_replace(
                        ['{{' . $itemName . '}}', '{{ ' . $itemName . ' }}'],
                        $item,
                        $itemContent
                    );
                }
                $output .= $itemContent;
            }

            return $output;
        }, $template);

        return $template;
    }

    /**
     * Apply branding styles to HTML
     */
    protected function applyBrandingStyles(string $html, WhiteLabelConfig $config): string
    {
        $styles = $this->generateEmailStyles($config);

        // Insert styles into head or create head if not exists
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', "<style>{$styles}</style></head>", $html);
        } else {
            $html = "<html><head><style>{$styles}</style></head><body>{$html}</body></html>";
        }

        return $html;
    }

    /**
     * Generate email-specific styles
     */
    protected function generateEmailStyles(WhiteLabelConfig $config): string
    {
        $primaryColor = $config->getThemeVariable('primary_color', '#3b82f6');
        $secondaryColor = $config->getThemeVariable('secondary_color', '#1f2937');
        $textColor = $config->getThemeVariable('text_color', '#1f2937');
        $backgroundColor = $config->getThemeVariable('background_color', '#ffffff');

        $styles = "
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6;
                color: {$textColor};
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
            }
            .email-wrapper {
                max-width: 600px;
                margin: 0 auto;
                background-color: {$backgroundColor};
            }
            .email-header {
                background-color: {$primaryColor};
                padding: 30px;
                text-align: center;
            }
            .email-header img {
                max-height: 50px;
                max-width: 200px;
            }
            .email-body {
                padding: 40px 30px;
            }
            .email-footer {
                background-color: #f9fafb;
                padding: 30px;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            h1, h2, h3 {
                color: {$secondaryColor};
                margin-top: 0;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background-color: {$primaryColor};
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: 600;
            }
            .btn:hover {
                opacity: 0.9;
            }
            .alert {
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .alert-success {
                background-color: #d4edda;
                border-color: #c3e6cb;
                color: #155724;
            }
            .alert-error {
                background-color: #f8d7da;
                border-color: #f5c6cb;
                color: #721c24;
            }
            .alert-warning {
                background-color: #fff3cd;
                border-color: #ffeeba;
                color: #856404;
            }
            .alert-info {
                background-color: #d1ecf1;
                border-color: #bee5eb;
                color: #0c5460;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
            }
            th {
                background-color: #f9fafb;
                font-weight: 600;
                color: {$secondaryColor};
            }
        ";

        // Add custom CSS if provided
        if ($config->custom_css) {
            $styles .= "\n/* Custom CSS */\n" . $config->custom_css;
        }

        return $styles;
    }

    /**
     * Inline CSS for email compatibility
     */
    protected function inlineCss(string $html, WhiteLabelConfig $config): string
    {
        // Extract styles from HTML
        preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $html, $matches);
        $css = implode("\n", $matches[1]);

        // Remove style tags
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);

        // Inline the CSS
        if (!empty($css)) {
            $html = $this->cssInliner->convert($html, $css);
        }

        return $html;
    }

    /**
     * Get welcome email template
     */
    protected function getWelcomeTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Welcome to {{ platform_name }}!</h1>
            <p>Hi {{ user_name }},</p>
            <p>Thank you for joining {{ platform_name }}. We\'re excited to have you on board!</p>
            <p>Your account has been successfully created. You can now access all the features of our platform.</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ login_url }}" class="btn">Get Started</a>
            </p>
            <p>If you have any questions, feel free to reach out to our support team.</p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get password reset email template
     */
    protected function getPasswordResetTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Password Reset Request</h1>
            <p>Hi {{ user_name }},</p>
            <p>We received a request to reset your password for your {{ platform_name }} account.</p>
            <p>Click the button below to reset your password. This link will expire in {{ expiry_hours }} hours.</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ reset_url }}" class="btn">Reset Password</a>
            </p>
            <p>If you didn\'t request this password reset, please ignore this email. Your password won\'t be changed.</p>
            <p>For security reasons, this link will expire on {{ expiry_date }}.</p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get email verification template
     */
    protected function getEmailVerificationTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Verify Your Email Address</h1>
            <p>Hi {{ user_name }},</p>
            <p>Please verify your email address to complete your {{ platform_name }} account setup.</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ verification_url }}" class="btn">Verify Email</a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background: #f5f5f5; padding: 10px; border-radius: 5px;">
                {{ verification_url }}
            </p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get invitation email template
     */
    protected function getInvitationTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>You\'ve Been Invited!</h1>
            <p>Hi {{ invitee_name }},</p>
            <p>{{ inviter_name }} has invited you to join {{ organization_name }} on {{ platform_name }}.</p>
            <p>Click the button below to accept the invitation and create your account:</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ invitation_url }}" class="btn">Accept Invitation</a>
            </p>
            <p>This invitation will expire on {{ expiry_date }}.</p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get deployment success email template
     */
    protected function getDeploymentSuccessTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Deployment Successful! 🎉</h1>
            <div class="alert alert-success">
                <strong>{{ application_name }}</strong> has been successfully deployed.
            </div>
            <h3>Deployment Details:</h3>
            <table>
                <tr>
                    <th>Application</th>
                    <td>{{ application_name }}</td>
                </tr>
                <tr>
                    <th>Environment</th>
                    <td>{{ environment }}</td>
                </tr>
                <tr>
                    <th>Version</th>
                    <td>{{ version }}</td>
                </tr>
                <tr>
                    <th>Deployed At</th>
                    <td>{{ deployed_at }}</td>
                </tr>
                <tr>
                    <th>Deploy Time</th>
                    <td>{{ deploy_duration }}</td>
                </tr>
            </table>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ application_url }}" class="btn">View Application</a>
            </p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get deployment failure email template
     */
    protected function getDeploymentFailureTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Deployment Failed ⚠️</h1>
            <div class="alert alert-error">
                <strong>{{ application_name }}</strong> deployment has failed.
            </div>
            <h3>Error Details:</h3>
            <table>
                <tr>
                    <th>Application</th>
                    <td>{{ application_name }}</td>
                </tr>
                <tr>
                    <th>Environment</th>
                    <td>{{ environment }}</td>
                </tr>
                <tr>
                    <th>Failed At</th>
                    <td>{{ failed_at }}</td>
                </tr>
                <tr>
                    <th>Error Message</th>
                    <td>{{ error_message }}</td>
                </tr>
            </table>
            <h3>Error Log:</h3>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;">{{ error_log }}</pre>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ deployment_logs_url }}" class="btn">View Full Logs</a>
            </p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get server unreachable email template
     */
    protected function getServerUnreachableTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Server Unreachable ⚠️</h1>
            <div class="alert alert-warning">
                We\'re unable to reach your server <strong>{{ server_name }}</strong>.
            </div>
            <h3>Server Details:</h3>
            <table>
                <tr>
                    <th>Server Name</th>
                    <td>{{ server_name }}</td>
                </tr>
                <tr>
                    <th>IP Address</th>
                    <td>{{ server_ip }}</td>
                </tr>
                <tr>
                    <th>Last Seen</th>
                    <td>{{ last_seen }}</td>
                </tr>
                <tr>
                    <th>Applications Affected</th>
                    <td>{{ affected_applications }}</td>
                </tr>
            </table>
            <p>Please check the server status and network connectivity.</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ server_dashboard_url }}" class="btn">View Server Dashboard</a>
            </p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get backup success email template
     */
    protected function getBackupSuccessTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Backup Completed Successfully ✅</h1>
            <div class="alert alert-success">
                Your backup for <strong>{{ resource_name }}</strong> has been completed successfully.
            </div>
            <h3>Backup Details:</h3>
            <table>
                <tr>
                    <th>Resource</th>
                    <td>{{ resource_name }}</td>
                </tr>
                <tr>
                    <th>Type</th>
                    <td>{{ backup_type }}</td>
                </tr>
                <tr>
                    <th>Size</th>
                    <td>{{ backup_size }}</td>
                </tr>
                <tr>
                    <th>Completed At</th>
                    <td>{{ completed_at }}</td>
                </tr>
                <tr>
                    <th>Duration</th>
                    <td>{{ backup_duration }}</td>
                </tr>
                <tr>
                    <th>Storage Location</th>
                    <td>{{ storage_location }}</td>
                </tr>
            </table>
            <p>Your data is safely backed up and can be restored if needed.</p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get backup failure email template
     */
    protected function getBackupFailureTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>Backup Failed ⚠️</h1>
            <div class="alert alert-error">
                The backup for <strong>{{ resource_name }}</strong> has failed.
            </div>
            <h3>Failure Details:</h3>
            <table>
                <tr>
                    <th>Resource</th>
                    <td>{{ resource_name }}</td>
                </tr>
                <tr>
                    <th>Type</th>
                    <td>{{ backup_type }}</td>
                </tr>
                <tr>
                    <th>Failed At</th>
                    <td>{{ failed_at }}</td>
                </tr>
                <tr>
                    <th>Error Message</th>
                    <td>{{ error_message }}</td>
                </tr>
            </table>
            <p>Please review the error and retry the backup operation.</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ backup_dashboard_url }}" class="btn">View Backup Dashboard</a>
            </p>
            <p>Best regards,<br>The {{ platform_name }} Team</p>
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get generic email template
     */
    protected function getGenericTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ logo_url }}" alt="{{ platform_name }}">
        </div>
        <div class="email-body">
            <h1>{{ subject }}</h1>
            {{ content }}
        </div>
        <div class="email-footer">
            @unless(hide_branding)
            <p>Powered by Coolify</p>
            @endunless
            <p>&copy; {{ current_year }} {{ platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Preview email template
     */
    public function previewTemplate(WhiteLabelConfig $config, string $templateName, array $sampleData = []): array
    {
        // Generate sample data if not provided
        if (empty($sampleData)) {
            $sampleData = $this->getSampleData($templateName);
        }

        // Generate HTML
        $html = $this->generateTemplate($config, $templateName, $sampleData);

        // Generate text version
        $text = $this->generateTextVersion($html);

        return [
            'html' => $html,
            'text' => $text,
            'subject' => $this->getTemplateSubject($templateName, $sampleData),
        ];
    }

    /**
     * Generate text version of email
     */
    protected function generateTextVersion(string $html): string
    {
        // Remove HTML tags
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);

        return trim($text);
    }

    /**
     * Get template subject
     */
    protected function getTemplateSubject(string $templateName, array $data): string
    {
        $subjects = [
            'welcome' => 'Welcome to ' . ($data['platform_name'] ?? 'Our Platform'),
            'password_reset' => 'Password Reset Request',
            'email_verification' => 'Verify Your Email Address',
            'invitation' => 'You\'ve Been Invited to Join ' . ($data['organization_name'] ?? 'Our Organization'),
            'deployment_success' => 'Deployment Successful: ' . ($data['application_name'] ?? 'Your Application'),
            'deployment_failure' => 'Deployment Failed: ' . ($data['application_name'] ?? 'Your Application'),
            'server_unreachable' => 'Server Alert: ' . ($data['server_name'] ?? 'Server') . ' is Unreachable',
            'backup_success' => 'Backup Completed Successfully',
            'backup_failure' => 'Backup Failed: Action Required',
        ];

        return $subjects[$templateName] ?? 'Notification from ' . ($data['platform_name'] ?? 'Platform');
    }

    /**
     * Get sample data for template preview
     */
    protected function getSampleData(string $templateName): array
    {
        $baseData = [
            'user_name' => 'John Doe',
            'platform_name' => 'Coolify Enterprise',
            'organization_name' => 'Acme Corporation',
            'current_year' => date('Y'),
        ];

        $templateSpecificData = [
            'welcome' => [
                'login_url' => 'https://example.com/login',
            ],
            'password_reset' => [
                'reset_url' => 'https://example.com/reset?token=abc123',
                'expiry_hours' => 24,
                'expiry_date' => now()->addHours(24)->format('F j, Y at g:i A'),
            ],
            'email_verification' => [
                'verification_url' => 'https://example.com/verify?token=xyz789',
            ],
            'invitation' => [
                'inviter_name' => 'Jane Smith',
                'invitee_name' => 'John Doe',
                'invitation_url' => 'https://example.com/invite?token=inv456',
                'expiry_date' => now()->addDays(7)->format('F j, Y'),
            ],
            'deployment_success' => [
                'application_name' => 'My Awesome App',
                'environment' => 'Production',
                'version' => 'v1.2.3',
                'deployed_at' => now()->format('F j, Y at g:i A'),
                'deploy_duration' => '2 minutes 15 seconds',
                'application_url' => 'https://myapp.example.com',
            ],
            'deployment_failure' => [
                'application_name' => 'My Awesome App',
                'environment' => 'Production',
                'failed_at' => now()->format('F j, Y at g:i A'),
                'error_message' => 'Build failed: npm install exited with code 1',
                'error_log' => 'npm ERR! code ERESOLVE...',
                'deployment_logs_url' => 'https://example.com/deployments/123/logs',
            ],
            'server_unreachable' => [
                'server_name' => 'Production Server 1',
                'server_ip' => '192.168.1.100',
                'last_seen' => now()->subMinutes(30)->format('F j, Y at g:i A'),
                'affected_applications' => '3',
                'server_dashboard_url' => 'https://example.com/servers/1',
            ],
            'backup_success' => [
                'resource_name' => 'Production Database',
                'backup_type' => 'Full Backup',
                'backup_size' => '2.5 GB',
                'completed_at' => now()->format('F j, Y at g:i A'),
                'backup_duration' => '5 minutes 30 seconds',
                'storage_location' => 'Amazon S3',
            ],
            'backup_failure' => [
                'resource_name' => 'Production Database',
                'backup_type' => 'Full Backup',
                'failed_at' => now()->format('F j, Y at g:i A'),
                'error_message' => 'Storage quota exceeded',
                'backup_dashboard_url' => 'https://example.com/backups',
            ],
        ];

        return array_merge($baseData, $templateSpecificData[$templateName] ?? []);
    }
}