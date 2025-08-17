# Design Document

## Overview

This design document outlines the architectural transformation of Coolify into an enterprise-grade cloud deployment and management platform. The enhanced system will maintain Coolify's core strengths in application deployment while adding comprehensive enterprise features including multi-tenant architecture, licensing systems, payment processing, domain management, and advanced cloud provider integration.

### Key Architectural Principles

1. **Preserve Coolify's Core Excellence**: Maintain the robust application deployment engine that makes Coolify powerful
2. **Terraform + Coolify Hybrid**: Use Terraform for infrastructure provisioning, Coolify for application management
3. **Multi-Tenant by Design**: Support hierarchical organizations with proper data isolation
4. **API-First Architecture**: All functionality accessible via well-documented APIs
5. **White-Label Ready**: Complete customization capabilities for resellers

## Architecture

### High-Level System Architecture

```mermaid
graph TB
    subgraph "Frontend Layer"
        UI[Enhanced Livewire UI]
        API[REST API Layer]
        WL[White-Label Engine]
    end
    
    subgraph "Application Layer"
        AUTH[Authentication & MFA]
        RBAC[Role-Based Access Control]
        LIC[Licensing Engine]
        PAY[Payment Processing]
        DOM[Domain Management]
    end
    
    subgraph "Infrastructure Layer"
        TF[Terraform Engine]
        COOL[Coolify Deployment Engine]
        PROV[Cloud Provider APIs]
    end
    
    subgraph "Data Layer"
        PG[(PostgreSQL)]
        REDIS[(Redis Cache)]
        FILES[File Storage]
    end
    
    UI --> AUTH
    API --> RBAC
    WL --> UI
    
    AUTH --> LIC
    RBAC --> PAY
    LIC --> DOM
    
    PAY --> TF
    DOM --> COOL
    TF --> PROV
    
    AUTH --> PG
    RBAC --> REDIS
    COOL --> FILES
```

### Enhanced Database Schema

The existing Coolify database will be extended with new tables for enterprise functionality while preserving all current data structures.

#### Core Enterprise Tables

```sql
-- Organization hierarchy for multi-tenancy
CREATE TABLE organizations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    hierarchy_type VARCHAR(50) NOT NULL CHECK (hierarchy_type IN ('top_branch', 'master_branch', 'sub_user', 'end_user')),
    hierarchy_level INTEGER DEFAULT 0,
    parent_organization_id UUID REFERENCES organizations(id),
    branding_config JSONB DEFAULT '{}',
    feature_flags JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Enhanced user management with organization relationships
CREATE TABLE organization_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) NOT NULL DEFAULT 'member',
    permissions JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(organization_id, user_id)
);

-- Licensing system
CREATE TABLE enterprise_licenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    license_key VARCHAR(255) UNIQUE NOT NULL,
    license_type VARCHAR(50) NOT NULL, -- perpetual, subscription, trial
    license_tier VARCHAR(50) NOT NULL, -- basic, professional, enterprise
    features JSONB DEFAULT '{}',
    limits JSONB DEFAULT '{}', -- user limits, domain limits, resource limits
    issued_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP,
    last_validated_at TIMESTAMP,
    authorized_domains JSONB DEFAULT '[]',
    status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active', 'expired', 'suspended', 'revoked')),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- White-label configuration
CREATE TABLE white_label_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    platform_name VARCHAR(255) DEFAULT 'Coolify',
    logo_url TEXT,
    theme_config JSONB DEFAULT '{}',
    custom_domains JSONB DEFAULT '[]',
    hide_coolify_branding BOOLEAN DEFAULT false,
    custom_email_templates JSONB DEFAULT '{}',
    custom_css TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(organization_id)
);

-- Cloud provider credentials (encrypted)
CREATE TABLE cloud_provider_credentials (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    provider_name VARCHAR(50) NOT NULL, -- aws, gcp, azure, digitalocean, hetzner
    provider_region VARCHAR(100),
    credentials JSONB NOT NULL, -- encrypted API keys, secrets
    is_active BOOLEAN DEFAULT true,
    last_validated_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Enhanced server management with Terraform integration
CREATE TABLE terraform_deployments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    server_id INTEGER REFERENCES servers(id) ON DELETE CASCADE,
    provider_credential_id UUID REFERENCES cloud_provider_credentials(id),
    terraform_state JSONB,
    deployment_config JSONB NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Integration with Existing Coolify Models

#### Enhanced User Model

```php
// Extend existing User model
class User extends Authenticatable implements SendsEmail
{
    // ... existing code ...

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot('role', 'permissions', 'is_active')
            ->withTimestamps();
    }

    public function currentOrganization()
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function canPerformAction($action, $resource = null)
    {
        $organization = $this->currentOrganization;
        if (!$organization) return false;

        return $organization->canUserPerformAction($this, $action, $resource);
    }

    public function hasLicenseFeature($feature)
    {
        return $this->currentOrganization?->activeLicense?->hasFeature($feature) ?? false;
    }
}
```

#### Enhanced Server Model

```php
// Extend existing Server model
class Server extends BaseModel
{
    // ... existing code ...

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function terraformDeployment()
    {
        return $this->hasOne(TerraformDeployment::class);
    }

    public function cloudProviderCredential()
    {
        return $this->belongsTo(CloudProviderCredential::class, 'provider_credential_id');
    }

    public function isProvisionedByTerraform()
    {
        return $this->terraformDeployment !== null;
    }

    public function canBeManaged()
    {
        // Check if server is reachable and user has permissions
        return $this->settings->is_reachable && 
               auth()->user()->canPerformAction('manage_server', $this);
    }
}
```

## Components and Interfaces

### 1. Terraform Integration Service

```php
interface TerraformServiceInterface
{
    public function provisionInfrastructure(array $config, CloudProviderCredential $credentials): TerraformDeployment;
    public function destroyInfrastructure(TerraformDeployment $deployment): bool;
    public function getDeploymentStatus(TerraformDeployment $deployment): string;
    public function updateInfrastructure(TerraformDeployment $deployment, array $newConfig): bool;
}

class TerraformService implements TerraformServiceInterface
{
    public function provisionInfrastructure(array $config, CloudProviderCredential $credentials): TerraformDeployment
    {
        // 1. Generate Terraform configuration based on provider and config
        $terraformConfig = $this->generateTerraformConfig($config, $credentials);
        
        // 2. Execute terraform plan and apply
        $deployment = TerraformDeployment::create([
            'organization_id' => $credentials->organization_id,
            'provider_credential_id' => $credentials->id,
            'deployment_config' => $config,
            'status' => 'provisioning'
        ]);

        // 3. Run Terraform in isolated environment
        $result = $this->executeTerraform($terraformConfig, $deployment);
        
        // 4. If successful, register server with Coolify
        if ($result['success']) {
            $server = $this->registerServerWithCoolify($result['outputs'], $deployment);
            $deployment->update(['server_id' => $server->id, 'status' => 'completed']);
        } else {
            $deployment->update(['status' => 'failed', 'error_message' => $result['error']]);
        }

        return $deployment;
    }

    private function generateTerraformConfig(array $config, CloudProviderCredential $credentials): string
    {
        $provider = $credentials->provider_name;
        $template = $this->getProviderTemplate($provider);
        
        return $this->renderTemplate($template, [
            'credentials' => decrypt($credentials->credentials),
            'config' => $config,
            'organization_id' => $credentials->organization_id
        ]);
    }

    private function registerServerWithCoolify(array $outputs, TerraformDeployment $deployment): Server
    {
        return Server::create([
            'name' => $outputs['server_name'],
            'ip' => $outputs['public_ip'],
            'private_ip' => $outputs['private_ip'] ?? null,
            'user' => 'root',
            'port' => 22,
            'organization_id' => $deployment->organization_id,
            'team_id' => $deployment->organization->getTeamId(), // Map to existing team system
            'private_key_id' => $this->createSSHKey($outputs['ssh_private_key']),
        ]);
    }
}
```

### 2. Licensing Engine

```php
interface LicensingServiceInterface
{
    public function validateLicense(string $licenseKey, string $domain = null): LicenseValidationResult;
    public function issueLicense(Organization $organization, array $config): EnterpriseLicense;
    public function revokeLicense(EnterpriseLicense $license): bool;
    public function checkUsageLimits(EnterpriseLicense $license): array;
}

class LicensingService implements LicensingServiceInterface
{
    public function validateLicense(string $licenseKey, string $domain = null): LicenseValidationResult
    {
        $license = EnterpriseLicense::where('license_key', $licenseKey)
            ->where('status', 'active')
            ->first();

        if (!$license) {
            return new LicenseValidationResult(false, 'License not found');
        }

        // Check expiration
        if ($license->expires_at && $license->expires_at->isPast()) {
            return new LicenseValidationResult(false, 'License expired');
        }

        // Check domain authorization
        if ($domain && !$this->isDomainAuthorized($license, $domain)) {
            return new LicenseValidationResult(false, 'Domain not authorized');
        }

        // Check usage limits
        $usageCheck = $this->checkUsageLimits($license);
        if (!$usageCheck['within_limits']) {
            return new LicenseValidationResult(false, 'Usage limits exceeded: ' . implode(', ', $usageCheck['violations']));
        }

        // Update validation timestamp
        $license->update(['last_validated_at' => now()]);

        return new LicenseValidationResult(true, 'License valid', $license);
    }

    public function checkUsageLimits(EnterpriseLicense $license): array
    {
        $limits = $license->limits;
        $organization = $license->organization;
        $violations = [];

        // Check user count
        if (isset($limits['max_users'])) {
            $userCount = $organization->users()->count();
            if ($userCount > $limits['max_users']) {
                $violations[] = "User count ({$userCount}) exceeds limit ({$limits['max_users']})";
            }
        }

        // Check server count
        if (isset($limits['max_servers'])) {
            $serverCount = $organization->servers()->count();
            if ($serverCount > $limits['max_servers']) {
                $violations[] = "Server count ({$serverCount}) exceeds limit ({$limits['max_servers']})";
            }
        }

        // Check domain count
        if (isset($limits['max_domains'])) {
            $domainCount = $organization->domains()->count();
            if ($domainCount > $limits['max_domains']) {
                $violations[] = "Domain count ({$domainCount}) exceeds limit ({$limits['max_domains']})";
            }
        }

        return [
            'within_limits' => empty($violations),
            'violations' => $violations,
            'usage' => [
                'users' => $organization->users()->count(),
                'servers' => $organization->servers()->count(),
                'domains' => $organization->domains()->count(),
            ]
        ];
    }
}
```

### 3. White-Label Service

```php
interface WhiteLabelServiceInterface
{
    public function getConfigForOrganization(string $organizationId): WhiteLabelConfig;
    public function updateBranding(string $organizationId, array $config): WhiteLabelConfig;
    public function renderWithBranding(string $view, array $data, Organization $organization): string;
}

class WhiteLabelService implements WhiteLabelServiceInterface
{
    public function getConfigForOrganization(string $organizationId): WhiteLabelConfig
    {
        $config = WhiteLabelConfig::where('organization_id', $organizationId)->first();
        
        if (!$config) {
            return $this->getDefaultConfig();
        }

        return $config;
    }

    public function updateBranding(string $organizationId, array $config): WhiteLabelConfig
    {
        return WhiteLabelConfig::updateOrCreate(
            ['organization_id' => $organizationId],
            [
                'platform_name' => $config['platform_name'] ?? 'Coolify',
                'logo_url' => $config['logo_url'],
                'theme_config' => $config['theme_config'] ?? [],
                'hide_coolify_branding' => $config['hide_coolify_branding'] ?? false,
                'custom_domains' => $config['custom_domains'] ?? [],
                'custom_css' => $config['custom_css'] ?? null,
            ]
        );
    }

    public function renderWithBranding(string $view, array $data, Organization $organization): string
    {
        $branding = $this->getConfigForOrganization($organization->id);
        
        $data['branding'] = $branding;
        $data['theme_vars'] = $this->generateThemeVariables($branding);
        
        return view($view, $data)->render();
    }

    private function generateThemeVariables(WhiteLabelConfig $config): array
    {
        $theme = $config->theme_config;
        
        return [
            '--primary-color' => $theme['primary_color'] ?? '#3b82f6',
            '--secondary-color' => $theme['secondary_color'] ?? '#1f2937',
            '--accent-color' => $theme['accent_color'] ?? '#10b981',
            '--background-color' => $theme['background_color'] ?? '#ffffff',
            '--text-color' => $theme['text_color'] ?? '#1f2937',
        ];
    }
}
```

### 4. Enhanced Payment Processing

```php
interface PaymentServiceInterface
{
    public function processPayment(Organization $organization, PaymentRequest $request): PaymentResult;
    public function createSubscription(Organization $organization, SubscriptionRequest $request): Subscription;
    public function handleWebhook(string $provider, array $payload): void;
}

class PaymentService implements PaymentServiceInterface
{
    protected array $gateways = [];

    public function __construct()
    {
        $this->initializeGateways();
    }

    public function processPayment(Organization $organization, PaymentRequest $request): PaymentResult
    {
        $gateway = $this->getGateway($request->gateway);
        
        try {
            // Validate license allows payment processing
            $license = $organization->activeLicense;
            if (!$license || !$license->hasFeature('payment_processing')) {
                throw new PaymentException('Payment processing not allowed for this license');
            }

            $result = $gateway->charge([
                'amount' => $request->amount,
                'currency' => $request->currency,
                'payment_method' => $request->payment_method,
                'metadata' => [
                    'organization_id' => $organization->id,
                    'license_key' => $license->license_key,
                    'service_type' => $request->service_type,
                ]
            ]);

            // Log transaction
            $this->logTransaction($organization, $result, $request);

            // If successful, provision resources or extend services
            if ($result->isSuccessful()) {
                $this->handleSuccessfulPayment($organization, $request, $result);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logFailedTransaction($organization, $e, $request);
            throw new PaymentException('Payment processing failed: ' . $e->getMessage());
        }
    }

    private function handleSuccessfulPayment(Organization $organization, PaymentRequest $request, PaymentResult $result): void
    {
        switch ($request->service_type) {
            case 'infrastructure':
                dispatch(new ProvisionInfrastructureJob($organization, $request->metadata));
                break;
            case 'domain':
                dispatch(new PurchaseDomainJob($organization, $request->metadata));
                break;
            case 'license_upgrade':
                dispatch(new UpgradeLicenseJob($organization, $request->metadata));
                break;
            case 'subscription':
                $this->extendSubscription($organization, $request->metadata);
                break;
        }
    }
}
```

## Data Models

### Core Enterprise Models

```php
class Organization extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'hierarchy_type', 'hierarchy_level', 
        'parent_organization_id', 'branding_config', 'feature_flags'
    ];

    protected $casts = [
        'branding_config' => 'array',
        'feature_flags' => 'array',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_organization_id');
    }

    public function children()
    {
        return $this->hasMany(Organization::class, 'parent_organization_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role', 'permissions', 'is_active');
    }

    public function activeLicense()
    {
        return $this->hasOne(EnterpriseLicense::class)->where('status', 'active');
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(Application::class, Server::class);
    }

    // Business Logic
    public function canUserPerformAction(User $user, string $action, $resource = null): bool
    {
        $userOrg = $this->users()->where('user_id', $user->id)->first();
        if (!$userOrg) return false;

        $role = $userOrg->pivot->role;
        $permissions = $userOrg->pivot->permissions ?? [];

        return $this->checkPermission($role, $permissions, $action, $resource);
    }

    public function hasFeature(string $feature): bool
    {
        return $this->activeLicense?->hasFeature($feature) ?? false;
    }

    public function getUsageMetrics(): array
    {
        return [
            'users' => $this->users()->count(),
            'servers' => $this->servers()->count(),
            'applications' => $this->applications()->count(),
            'domains' => $this->domains()->count(),
        ];
    }
}

class EnterpriseLicense extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'license_key', 'license_type', 'license_tier',
        'features', 'limits', 'issued_at', 'expires_at', 'authorized_domains', 'status'
    ];

    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'authorized_domains' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function isValid(): bool
    {
        return $this->status === 'active' && 
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isWithinLimits(): bool
    {
        $service = app(LicensingService::class);
        $check = $service->checkUsageLimits($this);
        return $check['within_limits'];
    }
}
```

## Error Handling

### Centralized Exception Handling

```php
class EnterpriseExceptionHandler extends Handler
{
    protected $dontReport = [
        LicenseException::class,
        PaymentException::class,
        TerraformException::class,
    ];

    public function render($request, Throwable $exception)
    {
        // Handle license validation failures
        if ($exception instanceof LicenseException) {
            return $this->handleLicenseException($request, $exception);
        }

        // Handle payment processing errors
        if ($exception instanceof PaymentException) {
            return $this->handlePaymentException($request, $exception);
        }

        // Handle Terraform provisioning errors
        if ($exception instanceof TerraformException) {
            return $this->handleTerraformException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    private function handleLicenseException($request, LicenseException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'License validation failed',
                'message' => $exception->getMessage(),
                'code' => 'LICENSE_ERROR'
            ], 403);
        }

        return redirect()->route('license.invalid')
            ->with('error', $exception->getMessage());
    }
}

// Custom Exceptions
class LicenseException extends Exception {}
class PaymentException extends Exception {}
class TerraformException extends Exception {}
class OrganizationException extends Exception {}
```

## Testing Strategy

### Unit Testing Approach

```php
class LicensingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validates_active_license()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $service = new LicensingService();
        $result = $service->validateLicense($license->license_key);

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_expired_license()
    {
        $organization = Organization::factory()->create();
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $service = new LicensingService();
        $result = $service->validateLicense($license->license_key);

        $this->assertFalse($result->isValid());
        $this->assertStringContains('expired', $result->getMessage());
    }
}

class TerraformServiceTest extends TestCase
{
    public function test_provisions_aws_infrastructure()
    {
        $organization = Organization::factory()->create();
        $credentials = CloudProviderCredential::factory()->create([
            'organization_id' => $organization->id,
            'provider_name' => 'aws',
        ]);

        $config = [
            'instance_type' => 't3.micro',
            'region' => 'us-east-1',
            'ami' => 'ami-0abcdef1234567890',
        ];

        $service = new TerraformService();
        $deployment = $service->provisionInfrastructure($config, $credentials);

        $this->assertEquals('provisioning', $deployment->status);
        $this->assertNotNull($deployment->deployment_config);
    }
}
```

### Integration Testing

```php
class EnterpriseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_infrastructure_provisioning_workflow()
    {
        // 1. Create organization with valid license
        $organization = Organization::factory()->create(['hierarchy_type' => 'master_branch']);
        $license = EnterpriseLicense::factory()->create([
            'organization_id' => $organization->id,
            'features' => ['infrastructure_provisioning', 'terraform_integration'],
            'limits' => ['max_servers' => 10],
        ]);

        // 2. Add cloud provider credentials
        $credentials = CloudProviderCredential::factory()->create([
            'organization_id' => $organization->id,
            'provider_name' => 'aws',
        ]);

        // 3. Process payment for infrastructure
        $paymentRequest = new PaymentRequest([
            'amount' => 5000, // $50.00
            'currency' => 'usd',
            'service_type' => 'infrastructure',
            'gateway' => 'stripe',
        ]);

        $paymentService = new PaymentService();
        $paymentResult = $paymentService->processPayment($organization, $paymentRequest);

        $this->assertTrue($paymentResult->isSuccessful());

        // 4. Provision infrastructure via Terraform
        $terraformService = new TerraformService();
        $deployment = $terraformService->provisionInfrastructure([
            'instance_type' => 't3.small',
            'region' => 'us-east-1',
        ], $credentials);

        $this->assertEquals('completed', $deployment->fresh()->status);
        $this->assertNotNull($deployment->server);

        // 5. Verify server is registered with Coolify
        $server = $deployment->server;
        $this->assertEquals($organization->id, $server->organization_id);
        $this->assertTrue($server->canBeManaged());
    }
}
```

This design provides a comprehensive foundation for transforming Coolify into an enterprise platform while preserving its core strengths and adding the sophisticated features needed for a commercial hosting platform. The architecture is modular, scalable, and maintains clear separation of concerns between infrastructure provisioning (Terraform) and application management (Coolify).