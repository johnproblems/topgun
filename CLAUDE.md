# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Task Master AI Instructions
**Import Task Master's development workflow commands and guidelines, treat as if import is in the main CLAUDE.md file.**
@./.taskmaster/CLAUDE.md

## Project Overview

This is a **Coolify Enterprise Transformation Project** - transforming the existing Coolify fork into a comprehensive enterprise-grade cloud deployment and management platform. This is NOT standard Coolify development but a major architectural transformation.

### Key Transformation Goals

1. **Multi-Tenant Organization Hierarchy**: Replace team-based architecture with hierarchical organizations (Top Branch → Master Branch → Sub-Users → End Users)
2. **Terraform + Coolify Hybrid**: Use Terraform for infrastructure provisioning while preserving Coolify's application deployment excellence
3. **Enterprise Features**: Add licensing, payment processing, white-label branding, domain management
4. **Vue.js + Inertia.js Frontend**: Modern reactive frontend alongside existing Livewire components
5. **Real-time Resource Management**: Advanced capacity planning, build server optimization, organization quotas

## Development Context

### Project Status
- **Tasks 1-2 Completed**: Foundation setup (organizations, database schema) and licensing system
- **Current Focus**: Tasks 3+ (white-label branding, Terraform integration, payment processing)
- **Architecture**: Laravel 12 + Vue.js 3 + Inertia.js + existing Livewire components

### Key Reference Documents
- **Requirements**: `.kiro/specs/coolify-enterprise-transformation/requirements.md`
- **Design**: `.kiro/specs/coolify-enterprise-transformation/design.md` 
- **Implementation Plan**: `.kiro/specs/coolify-enterprise-transformation/tasks.md`
- **Architecture Guide**: `.kiro/steering/application-architecture.md`

## Technology Stack

### Backend (Enhanced)
- **Laravel 12** - Core framework (existing)
- **PostgreSQL 15** - Primary database (existing)
- **Redis 7** - Caching/queues (existing)
- **New Enterprise Services**: LicensingService, TerraformService, PaymentService, WhiteLabelService

### Frontend (Hybrid)
- **Livewire 3.6** - Server-side components (existing)
- **Vue.js 3.5** + **Inertia.js** - New reactive components for enterprise features
- **Alpine.js** - Client-side interactivity (existing)
- **Tailwind CSS 4.1** - Utility-first styling (existing)

### Enterprise Infrastructure
- **Terraform** - Cloud infrastructure provisioning (NEW)
- **Multi-Cloud Support** - AWS, GCP, Azure, DigitalOcean, Hetzner (NEW)
- **Docker** - Container orchestration (existing, enhanced)

## Development Commands

### Setup (Enterprise Fork)
```bash
# Standard Laravel setup
composer install
npm install
php artisan key:generate

# Run enterprise migrations
php artisan migrate

# Seed enterprise data
php artisan db:seed --class=EnterpriseSeeder

# Build Vue.js components
npm run dev

# Start services
php artisan serve
php artisan queue:work
php artisan reverb:start # WebSockets
```

### Code Quality
```bash
# PHP formatting and analysis
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/rector process --dry-run

# Run tests
./vendor/bin/pest
./vendor/bin/pest --coverage
```

### Vue.js Development
```bash
# Vue component development
npm run dev # Hot reload
npm run build # Production build

# Vue component testing
npm run test # If configured
```

## Architecture Overview

### Core Enterprise Models (NEW)
- **Organization**: Hierarchical multi-tenant structure
- **EnterpriseLicense**: Feature flags and usage limits
- **CloudProviderCredential**: Encrypted cloud API keys
- **TerraformDeployment**: Infrastructure provisioning state
- **WhiteLabelConfig**: Branding and customization
- **OrganizationResourceUsage**: Resource monitoring and quotas

### Enhanced Existing Models
- **User**: Extended with organization relationships
- **Server**: Enhanced with Terraform integration
- **Application**: Enhanced with capacity-aware deployment
- **Team**: Migrated to organization hierarchy

### Service Layer (NEW)
```php
// Core enterprise services
app/Services/Enterprise/
├── LicensingService.php        # License validation and management
├── TerraformService.php        # Infrastructure provisioning
├── PaymentService.php          # Multi-gateway payment processing
├── WhiteLabelService.php       # Branding and customization
├── OrganizationService.php     # Hierarchy management
├── CapacityManager.php         # Resource allocation
└── SystemResourceMonitor.php   # Real-time monitoring
```

### Frontend Architecture (Hybrid)

#### Livewire Components (Existing)
- Core application management
- Server monitoring
- Deployment workflows

#### Vue.js Components (NEW)
```
resources/js/Components/Enterprise/
├── Organization/
│   ├── OrganizationManager.vue
│   ├── OrganizationHierarchy.vue
│   └── OrganizationSwitcher.vue
├── License/
│   ├── LicenseManager.vue
│   ├── UsageMonitoring.vue
│   └── FeatureToggles.vue
├── Infrastructure/
│   ├── TerraformManager.vue
│   └── CloudProviderCredentials.vue
└── WhiteLabel/
    ├── BrandingManager.vue
    └── ThemeCustomizer.vue
```

## Database Schema (Enhanced)

### Enterprise Tables (NEW)
- `organizations` - Hierarchical organization structure
- `organization_users` - User-organization relationships with roles
- `enterprise_licenses` - License management with feature flags
- `white_label_configs` - Branding configuration
- `cloud_provider_credentials` - Encrypted cloud API keys
- `terraform_deployments` - Infrastructure provisioning tracking
- `server_resource_metrics` - Real-time resource monitoring
- `organization_resource_usage` - Organization-level resource quotas

### Enhanced Existing Tables
- Extended `users` table with organization relationships
- Enhanced `servers` table with Terraform integration
- Modified foreign keys to support organization hierarchy

## Development Patterns

### Enterprise Service Pattern
```php
class LicensingService implements LicensingServiceInterface
{
    public function validateLicense(string $licenseKey, string $domain = null): LicenseValidationResult
    {
        // License validation with domain checking
        // Usage limit enforcement
        // Feature flag validation
    }
}
```

### Vue.js + Inertia.js Pattern
```php
// Controller
class OrganizationController extends Controller
{
    public function index()
    {
        return Inertia::render('Enterprise/Organization/Index', [
            'organizations' => auth()->user()->organizations,
            'permissions' => auth()->user()->getAllPermissions(),
        ]);
    }
}
```

```vue
<!-- Vue Component -->
<template>
  <div class="organization-manager">
    <OrganizationHierarchy 
      :organizations="organizations" 
      @organization-selected="handleOrganizationSelect" 
    />
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'
import OrganizationHierarchy from './OrganizationHierarchy.vue'

const props = defineProps(['organizations', 'permissions'])
const emit = defineEmits(['organization-selected'])
</script>
```

### Resource Management Pattern
```php
class CapacityManager implements CapacityManagerInterface
{
    public function canServerHandleDeployment(Server $server, Application $app): bool
    {
        // Check CPU, memory, disk capacity
        // Consider current resource usage
        // Apply capacity buffers and safety margins
    }

    public function selectOptimalServer(Collection $servers, array $requirements): ?Server
    {
        // Score servers based on capacity and load
        // Select best-fit server for deployment
    }
}
```

## Key Implementation Areas

### 1. Organization Hierarchy
- Multi-level organization structure
- Role-based access control per organization
- Resource isolation and quota enforcement
- Cross-organization resource sharing

### 2. Licensing System ✅ COMPLETED
- License key generation and validation
- Feature flag enforcement
- Usage limit tracking
- Domain-based authorization

### 3. Terraform Integration (IN PROGRESS)
- Cloud provider credential management
- Infrastructure provisioning via Terraform
- Server registration with Coolify post-provisioning
- Multi-cloud support (AWS, GCP, Azure, etc.)

### 4. Resource Management (IN PROGRESS)
- Real-time resource monitoring
- Capacity-aware deployment decisions
- Build server load balancing
- Organization resource quotas

### 5. Payment Processing
- Multi-gateway support (Stripe, PayPal, etc.)
- Subscription management
- Usage-based billing
- Payment-triggered resource provisioning

### 6. White-Label Branding
- Custom branding per organization
- Dynamic theme configuration
- Custom domain support
- Branded email templates

## Testing Strategy

### Enterprise Test Structure
```
tests/
├── Enterprise/
│   ├── Feature/
│   │   ├── OrganizationManagementTest.php
│   │   ├── LicensingWorkflowTest.php
│   │   └── TerraformIntegrationTest.php
│   ├── Unit/
│   │   ├── LicensingServiceTest.php
│   │   ├── CapacityManagerTest.php
│   │   └── PaymentServiceTest.php
│   └── Browser/
│       ├── OrganizationManagementTest.php
│       └── LicenseManagementTest.php
```

### Testing Patterns
- Mock external services (Terraform, payment gateways)
- Test organization hierarchy and permissions
- Validate license enforcement across features
- Test resource capacity calculations
- Browser tests for Vue.js components

## Common Development Tasks

### Adding New Enterprise Features
1. Create service interface and implementation
2. Add database migrations if needed
3. Create Vue.js components for UI
4. Add Inertia.js routes and controllers
5. Write comprehensive tests
6. Update license feature flags

### Working with Vue.js Components
1. Components located in `resources/js/Components/Enterprise/`
2. Use Inertia.js for server communication
3. Follow existing component patterns
4. Build with `npm run dev` or `npm run build`

### Enterprise Service Development
1. Create interface in `app/Contracts/`
2. Implement service in `app/Services/Enterprise/`
3. Register in service provider
4. Add comprehensive error handling
5. Create unit and integration tests

## Security Considerations

### Data Isolation
- Organization-based data scoping
- Encrypted sensitive data (API keys, credentials)
- Role-based access control
- Audit logging for all actions

### API Security
- Sanctum token authentication
- Rate limiting per organization tier
- Request validation and sanitization
- CORS configuration for enterprise domains

## Performance Guidelines

### Database Optimization
- Organization-scoped queries with proper indexing
- Eager loading for complex relationships
- Efficient resource usage calculations
- Proper caching for license validations

### Frontend Performance
- Vue.js component lazy loading
- Efficient WebSocket connections
- Resource monitoring data pagination
- Optimized asset loading

## Deployment Considerations

### Environment Variables
```bash
# Enterprise-specific configuration
TERRAFORM_BINARY_PATH=/usr/local/bin/terraform
PAYMENT_STRIPE_SECRET_KEY=sk_test_...
PAYMENT_PAYPAL_CLIENT_ID=...
LICENSE_ENCRYPTION_KEY=...
ORGANIZATION_DEFAULT_QUOTAS=...
```

### Required Services
- PostgreSQL 15+ (primary database)
- Redis 7+ (caching, queues, sessions)
- Terraform (infrastructure provisioning)
- Docker (container management)
- WebSocket server (real-time features)

This is a major architectural transformation preserving Coolify's deployment excellence while adding comprehensive enterprise features. Focus on maintaining existing functionality while carefully implementing the new organizational hierarchy and enterprise capabilities.