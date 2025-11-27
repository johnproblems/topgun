# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

**Coolify Enterprise Transformation** - This repository contains a comprehensive enterprise-grade transformation of Coolify, the open-source self-hostable alternative to Heroku/Netlify/Vercel. The enhanced platform maintains Coolify's core deployment excellence while adding enterprise features including multi-tenant architecture, licensing systems, payment processing, domain management, and advanced cloud provider integration using Terraform.

### Key Architectural Insight
The transformation leverages **Terraform for infrastructure provisioning** (using customer API keys) while preserving **Coolify's excellent application deployment and management** capabilities. This creates a clear separation of concerns:
- **Terraform handles infrastructure** (server creation, networking, security groups)
- **Coolify handles applications** (deployment, management, monitoring)

### Enterprise Features Being Added
- **Multi-tenant organization hierarchy** (Top Branch → Master Branch → Sub-Users → End Users)
- **Comprehensive licensing system** with feature flags and usage limits
- **White-label branding** for resellers and hosting providers
- **Payment processing** with multiple gateways (Stripe, PayPal, Authorize.Net)
- **Domain management integration** (GoDaddy, Namecheap, Cloudflare)
- **Enhanced API system** with rate limiting and documentation
- **Multi-factor authentication** and advanced security features
- **Usage tracking and analytics** with cost optimization
- **Enhanced deployment pipeline** with blue-green deployments

## Current Architecture

### Backend Framework
- **Laravel 12** with PHP 8.4+ 
- **PostgreSQL 15** for primary database (being extended with enterprise tables)
- **Redis 7** for caching and real-time features
- **Soketi** for WebSocket server
- **Action Pattern** using `lorisleiva/laravel-actions` for business logic
- **Multi-tenant data isolation** at the database level

### Frontend Stack
- **Livewire 3.6+** with **Alpine.js** for reactive interfaces
- **Blade templating** with dynamic white-label theming
- **Tailwind CSS 4.1+** with customizable theme variables
- **Monaco Editor** for code editing
- **XTerm.js** for terminal components

### Core Domain Models (Extended for Enterprise)
- **Organization** - Multi-tenant hierarchy with parent/child relationships
- **EnterpriseLicense** - Feature flags, limits, and validation system
- **User** (Enhanced) - Organization relationships and permission checking
- **Application/Server** (Enhanced) - Organization scoping and Terraform integration
- **WhiteLabelConfig** - Branding and theme customization
- **CloudProviderCredential** - Encrypted API keys for AWS, GCP, Azure, etc.
- **TerraformDeployment** - Infrastructure provisioning tracking

## Development Commands

### Environment Setup
```bash
# Development environment with Docker (recommended)
./dev.sh start                    # Start all services
./dev.sh watch                    # Start backend file watcher for hot-reload
./dev.sh logs [service]           # View logs
./dev.sh shell                    # Open shell in Coolify container
./dev.sh db                       # Connect to database

# Native development
composer install                  # Install PHP dependencies
npm install                       # Install Node.js dependencies
php artisan serve                 # Start Laravel dev server
npm run dev                       # Start Vite dev server for frontend assets
php artisan queue:work            # Process background jobs
```

### Database Operations (Enterprise Extensions)
```bash
# Run enterprise migrations
php artisan migrate               # Apply all migrations including enterprise tables

# Seed enterprise data for development
php artisan db:seed --class=OrganizationSeeder
php artisan db:seed --class=EnterpriseLicenseSeeder
php artisan db:seed --class=WhiteLabelConfigSeeder

# Enterprise-specific migrations
php artisan make:migration create_organizations_table
php artisan make:migration create_enterprise_licenses_table
php artisan make:migration create_white_label_configs_table
php artisan make:migration create_cloud_provider_credentials_table
```

### Code Quality & Testing
```bash
# Code formatting and analysis
./vendor/bin/pint                 # PHP code style fixer (Laravel Pint)
./vendor/bin/rector process       # PHP automated refactoring
./vendor/bin/phpstan analyse      # Static analysis

# Testing framework: Pest PHP (comprehensive enterprise test suite)
./vendor/bin/pest                 # Run all tests
./vendor/bin/pest --coverage      # Run with coverage
./vendor/bin/pest tests/Feature/Enterprise  # Run enterprise feature tests
./vendor/bin/pest tests/Unit/Services       # Run service unit tests

# Browser testing with Laravel Dusk (including white-label UI tests)
php artisan dusk                  # Run browser tests
php artisan dusk tests/Browser/Enterprise  # Run enterprise browser tests
```

### Enterprise-Specific Commands
```bash
# License management
php artisan license:generate {organization_id}  # Generate new license
php artisan license:validate {license_key}      # Validate license
php artisan license:check-limits                # Check usage limits

# Organization management  
php artisan org:create "Company Name" --type=master_branch
php artisan org:assign-user {user_id} {org_id} --role=admin

# Terraform operations (when implemented)
php artisan terraform:provision {server_config}
php artisan terraform:destroy {deployment_id}
php artisan terraform:status {deployment_id}

# White-label operations
php artisan branding:update {org_id}
php artisan branding:generate-css {org_id}
```

## Enterprise Architecture Patterns

### Multi-Tenant Data Isolation
```php
// All models automatically scoped to organization
class Application extends BaseModel 
{
    public function scopeForOrganization($query, Organization $org) 
    {
        return $query->whereHas('server.organization', function ($q) use ($org) {
            $q->where('id', $org->id);
        });
    }
}

// Usage in controllers
$applications = Application::forOrganization(auth()->user()->currentOrganization)->get();
```

### Licensing System Integration
```php
// Feature checking throughout the application
if (!auth()->user()->hasLicenseFeature('terraform_provisioning')) {
    throw new LicenseException('Terraform provisioning requires upgraded license');
}

// Usage limit enforcement
$licenseCheck = app(LicensingService::class)->checkUsageLimits($organization->activeLicense);
if (!$licenseCheck['within_limits']) {
    return response()->json(['error' => 'Usage limits exceeded'], 403);
}
```

### White-Label Theming
```php
// Dynamic branding in views
@extends('layouts.app', ['branding' => $organization->whiteLabelConfig])

// CSS variable generation
:root {
    --primary-color: {{ $branding->theme_config['primary_color'] ?? '#3b82f6' }};
    --platform-name: "{{ $branding->platform_name ?? 'Coolify' }}";
}
```

### Terraform + Coolify Integration
```php
// Infrastructure provisioning workflow
$deployment = app(TerraformService::class)->provisionInfrastructure($config, $credentials);
// Returns TerraformDeployment with server automatically registered in Coolify

// Server management remains unchanged
$server = $deployment->server;
$server->applications()->create($appConfig);  // Uses existing Coolify deployment
```

## Implementation Plan Progress

The transformation is being implemented through 12 major phases:

### Phase 1: Foundation Setup ✅ (In Progress)
- [x] Create enterprise database migrations
- [x] Extend existing User and Server models
- [ ] Implement organization hierarchy and user association
- [ ] Create core enterprise models

### Phase 2: Licensing System (Next)
- [ ] Implement licensing validation and management
- [ ] Create license generation and usage tracking
- [ ] Integrate license checking with Coolify functionality

### Phase 3: White-Label Branding
- [ ] Implement comprehensive customization system
- [ ] Create dynamic theming and branding configuration
- [ ] Integrate branding with existing UI components

### Phase 4: Terraform Integration
- [ ] Implement Terraform-based infrastructure provisioning
- [ ] Create cloud provider API integration
- [ ] Integrate provisioned servers with Coolify management

### Phases 5-12: Advanced Features
- Payment processing and subscription management
- Domain management integration
- Enhanced API system with rate limiting  
- Multi-factor authentication and security
- Usage tracking and analytics
- Enhanced application deployment pipeline
- Testing and quality assurance
- Documentation and deployment

## Key Files and Directories

### Enterprise Specifications
- `.kiro/specs/coolify-enterprise-transformation/` - Complete transformation specifications
- `.kiro/specs/coolify-enterprise-transformation/requirements.md` - Detailed requirements (147 lines)
- `.kiro/specs/coolify-enterprise-transformation/design.md` - Architecture and design (830 lines)
- `.kiro/specs/coolify-enterprise-transformation/tasks.md` - Implementation plan (416 tasks)

### Existing Coolify Structure (Being Extended)
- `app/Models/` - Core models being extended with organization relationships
- `app/Livewire/` - UI components being enhanced with white-label support
- `app/Actions/` - Business logic being extended with enterprise features
- `database/migrations/` - Being extended with enterprise table migrations

### Development Configuration
- `dev.sh` - Development environment management script
- `docker-compose.dev-full.yml` - Full development stack
- `composer.json` - PHP dependencies including enterprise packages
- `package.json` - Frontend dependencies with white-label theming

## Testing Strategy

### Enterprise Testing Patterns
- **Multi-tenant isolation tests** - Ensure data separation between organizations
- **License validation tests** - Comprehensive license checking scenarios  
- **White-label UI tests** - Verify branding customization works correctly
- **Terraform integration tests** - Mock cloud provider API interactions
- **Payment processing tests** - Mock payment gateway interactions
- **End-to-end workflow tests** - Complete enterprise feature workflows

### Test Organization
- `tests/Feature/Enterprise/` - Enterprise feature integration tests
- `tests/Unit/Services/Enterprise/` - Enterprise service unit tests  
- `tests/Browser/Enterprise/` - Enterprise UI browser tests
- `tests/Traits/` - Enterprise testing utilities and helpers

## Security Considerations

### Multi-Tenant Security
- **Organization data isolation** - All queries scoped to user's organization
- **Permission-based access control** - Role and license-based feature access
- **Encrypted credential storage** - Cloud provider API keys encrypted at rest
- **Audit logging** - Comprehensive activity tracking for compliance

### License Security
- **Secure license generation** - Cryptographically signed license keys
- **Domain validation** - Licenses tied to authorized domains
- **Usage monitoring** - Real-time tracking to prevent abuse
- **Revocation capabilities** - Immediate license termination support

## Performance Considerations

### Database Optimization
- **Organization-based indexing** - Optimized for multi-tenant queries
- **License caching** - Frequently accessed license data cached
- **Usage metrics aggregation** - Efficient resource consumption tracking
- **Connection pooling** - Optimized for high-concurrency multi-tenant workloads

### Caching Strategy  
- **Organization context caching** - Reduce database lookups for user context
- **License validation caching** - Cache license status with TTL
- **White-label configuration caching** - Theme and branding data cached
- **Terraform state caching** - Infrastructure status cached for performance

This enterprise transformation maintains Coolify's core strengths while adding sophisticated multi-tenant, licensing, and white-label capabilities needed for a commercial hosting platform. The architecture preserves the existing deployment excellence while extending it with enterprise-grade features and infrastructure provisioning capabilities.
