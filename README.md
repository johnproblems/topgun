# Coolify Enterprise Transformation

**Enterprise-grade cloud deployment and management platform built on Coolify's foundation**

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=for-the-badge&logo=vue.js)](https://vuejs.org)
[![Terraform](https://img.shields.io/badge/Terraform-Latest-7B42BC?style=for-the-badge&logo=terraform)](https://terraform.io)

## About the Project

This project transforms the open-source Coolify platform into a comprehensive **enterprise-grade cloud deployment and management solution**. Built on Coolify's excellent application deployment foundation, we're adding enterprise features including multi-tenant organization hierarchies, Terraform-based infrastructure provisioning, white-label branding, and advanced resource management.

### What We're Building

- **Multi-Tenant Organization Hierarchy**: Replace team-based architecture with hierarchical organizations (Top Branch → Master Branch → Sub-Users → End Users)
- **Terraform + Coolify Hybrid**: Use Terraform for infrastructure provisioning while preserving Coolify's application deployment excellence
- **Enterprise Features**: Licensing system, payment processing, white-label branding, custom domain management
- **Modern Frontend**: Vue.js 3 + Inertia.js reactive components alongside existing Livewire
- **Real-time Resource Management**: Advanced capacity planning, build server optimization, organization quotas

## Technology Stack

### Backend
- **Laravel 12** - Core framework with enterprise services
- **PostgreSQL 15** - Primary database with hierarchical organization schema
- **Redis 7** - Caching, queues, and real-time features
- **Terraform** - Cloud infrastructure provisioning (NEW)
- **Docker** - Container orchestration (existing, enhanced)

### Frontend
- **Livewire 3.6** - Server-side components (existing)
- **Vue.js 3.5 + Inertia.js** - Reactive enterprise components (NEW)
- **Alpine.js** - Client-side interactivity (existing)
- **Tailwind CSS 4.1** - Utility-first styling (existing)

### Enterprise Services
- **LicensingService** - Feature flags and usage limits
- **TerraformService** - Multi-cloud infrastructure provisioning
- **PaymentService** - Multi-gateway payment processing
- **WhiteLabelService** - Branding and customization
- **CapacityManager** - Intelligent resource allocation
- **SystemResourceMonitor** - Real-time monitoring

## Quick Start

### 🚀 Option 1: Dev Container (Recommended)

**The fastest way to get started!** Use our pre-configured development container with all tools and services included:

1. Install [VS Code](https://code.visualstudio.com/) and [Remote - Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension
2. Open this folder in VS Code
3. Click "Reopen in Container" when prompted
4. Wait for automatic setup to complete
5. Start coding immediately! ✨

See [DEVCONTAINER.md](DEVCONTAINER.md) for full documentation.

### Option 2: Manual Installation

#### Prerequisites
- PHP 8.4+
- Node.js 20+
- PostgreSQL 15+
- Redis 7+
- Docker & Docker Compose
- Terraform (for infrastructure provisioning)

#### Installation

```bash
# Clone the repository
git clone <repository-url>
cd topgun

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure database and services in .env
# Then run migrations
php artisan migrate

# Seed enterprise data (organizations, licenses, etc.)
php artisan db:seed --class=EnterpriseSeeder

# Build frontend assets
npm run dev

# Start services
php artisan serve
php artisan queue:work
php artisan reverb:start # WebSockets for real-time features
```

### Development Commands

```bash
# Frontend development (hot reload)
npm run dev

# Production build
npm run build

# Code quality
./vendor/bin/pint                 # Format code
./vendor/bin/phpstan analyse      # Static analysis
./vendor/bin/rector process       # Code modernization

# Testing
./vendor/bin/pest                 # Run all tests
./vendor/bin/pest --coverage      # With coverage
./vendor/bin/pest --filter=test   # Run specific test
```

## Project Structure

```
topgun/
├── app/
│   ├── Actions/Enterprise/        # Enterprise business logic
│   ├── Models/                    # Eloquent models (enhanced)
│   ├── Services/Enterprise/       # Core enterprise services
│   ├── Livewire/                  # Livewire components (existing)
│   └── Http/Controllers/Enterprise/
├── resources/
│   ├── js/
│   │   ├── Components/Enterprise/ # Vue.js enterprise components
│   │   └── Pages/Enterprise/      # Inertia.js pages
│   └── views/
│       └── livewire/              # Livewire views
├── database/
│   ├── migrations/                # Database schema evolution
│   └── seeders/                   # Data seeding (including EnterpriseSeeder)
├── tests/
│   ├── Enterprise/Feature/        # Enterprise feature tests
│   ├── Enterprise/Unit/           # Enterprise unit tests
│   └── Enterprise/Browser/        # Browser tests for Vue components
├── .claude/                       # Claude Code configuration & PM workflow
└── .kiro/specs/                   # Enterprise transformation specs
```

## Key Features

### ✅ Completed (Tasks 1-2)
- **Multi-tenant Organization System**: Hierarchical organization structure with role-based access control
- **Enterprise Licensing**: License key validation, feature flags, usage limits, domain authorization
- **Database Schema**: Enhanced schema with organization hierarchy and resource tracking

### 🚧 In Progress (Tasks 3+)
- **White-Label Branding**: Custom branding, themes, and domain support per organization
- **Terraform Integration**: Multi-cloud infrastructure provisioning with server auto-registration
- **Payment Processing**: Stripe, PayPal integration with subscription management
- **Resource Management**: Real-time monitoring, capacity planning, quota enforcement
- **Vue.js Components**: Modern reactive UI for enterprise features

## Architecture Highlights

### Organization Hierarchy
```
Top Branch Organization
├── Master Branch Organizations
│   ├── Sub-User Organizations
│   │   └── End Users
│   └── End Users
└── End Users
```

### Service Layer Pattern
```php
// Example: License validation with domain checking
$result = app(LicensingService::class)
    ->validateLicense($licenseKey, $domain);

// Example: Terraform infrastructure provisioning
$deployment = app(TerraformService::class)
    ->provisionInfrastructure($cloudProvider, $config);

// Example: Capacity-aware server selection
$server = app(CapacityManager::class)
    ->selectOptimalServer($servers, $requirements);
```

### Vue.js + Inertia.js Integration
```php
// Controller
return Inertia::render('Enterprise/Organization/Index', [
    'organizations' => auth()->user()->organizations,
    'permissions' => auth()->user()->getAllPermissions(),
]);
```

```vue
<!-- Vue Component -->
<template>
  <OrganizationHierarchy
    :organizations="organizations"
    @organization-selected="handleSelect"
  />
</template>
```

## Development Workflow

### Using Claude Code PM Workflow

This project uses Claude Code's PM (Project Management) workflow for epic and task tracking:

```bash
# View project status
/pm/status

# List all epics
/pm/epic-list

# Show specific epic
/pm/epic-show <epic-number>

# Start working on an epic
/pm/epic-start <epic-number>

# Get next available task
/pm/next

# View PRD status
/pm/prd-status
```

See [.claude/CCPM_README.md](.claude/CCPM_README.md) for complete PM workflow documentation.

### Development Guidelines

1. **Follow Existing Patterns**: Check [CLAUDE.md](CLAUDE.md) for comprehensive development guidelines
2. **Enterprise Services**: Create interfaces in `app/Contracts/` and implementations in `app/Services/Enterprise/`
3. **Vue Components**: Follow existing patterns in `resources/js/Components/Enterprise/`
4. **Testing**: Write comprehensive tests for all new features
5. **Code Quality**: Run Pint, PHPStan, and Pest before committing

### Reference Documentation

- **Requirements**: [.kiro/specs/coolify-enterprise-transformation/requirements.md](.kiro/specs/coolify-enterprise-transformation/requirements.md)
- **Design**: [.kiro/specs/coolify-enterprise-transformation/design.md](.kiro/specs/coolify-enterprise-transformation/design.md)
- **Implementation Plan**: [.kiro/specs/coolify-enterprise-transformation/tasks.md](.kiro/specs/coolify-enterprise-transformation/tasks.md)
- **Architecture Guide**: [.kiro/steering/application-architecture.md](.kiro/steering/application-architecture.md)

## Testing

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suites
./vendor/bin/pest tests/Enterprise/Feature/
./vendor/bin/pest tests/Enterprise/Unit/

# Run with coverage
./vendor/bin/pest --coverage

# Run browser tests (Dusk)
php artisan dusk tests/Enterprise/Browser/
```

### Test Structure
- **Feature Tests**: Test complete user workflows and integrations
- **Unit Tests**: Test isolated service logic and calculations
- **Browser Tests**: Test Vue.js components and UI interactions

## Environment Configuration

### Required Environment Variables

```bash
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=coolify_enterprise
DB_USERNAME=postgres
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Enterprise Features
TERRAFORM_BINARY_PATH=/usr/local/bin/terraform
LICENSE_ENCRYPTION_KEY=
ORGANIZATION_DEFAULT_QUOTAS=

# Payment Gateways
PAYMENT_STRIPE_SECRET_KEY=
PAYMENT_STRIPE_PUBLISHABLE_KEY=
PAYMENT_PAYPAL_CLIENT_ID=
PAYMENT_PAYPAL_CLIENT_SECRET=

# Cloud Provider Credentials (encrypted in DB, these are for initial setup)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
```

## Security Considerations

- **Data Isolation**: Organization-scoped queries with proper indexing
- **Encrypted Credentials**: Cloud provider API keys encrypted at rest
- **Role-Based Access Control**: Comprehensive permission system per organization
- **Audit Logging**: All enterprise actions logged for compliance
- **API Security**: Sanctum token authentication with rate limiting per tier

## Performance Guidelines

- **Database Optimization**: Organization-scoped queries, proper indexing, eager loading
- **Frontend Performance**: Vue.js component lazy loading, optimized asset loading
- **Resource Monitoring**: Efficient data pagination and WebSocket connections
- **Caching Strategy**: Redis caching for license validations and resource calculations

## Contributing

This is an enterprise transformation project. For contribution guidelines:

1. Check existing tasks in `.taskmaster/tasks/`
2. Follow patterns in [CLAUDE.md](CLAUDE.md)
3. Write comprehensive tests
4. Ensure code quality (Pint, PHPStan)
5. Update documentation

## License

This project is built on Coolify's open-source foundation and is being transformed into an enterprise platform. See [LICENSE](LICENSE) for details.

## Project Status

**Current Phase**: Enterprise Feature Implementation (Tasks 3-10)

- ✅ Foundation Setup (Organization hierarchy, database schema)
- ✅ Licensing System (License validation, feature flags)
- 🚧 White-Label Branding (In progress)
- 🚧 Terraform Integration (In progress)
- ⏳ Payment Processing (Planned)
- ⏳ Advanced Resource Management (Planned)

See [.claude/epics/topgun/](.claude/epics/topgun/) for detailed epic and task breakdown.

## Acknowledgments

Built on the excellent foundation provided by [Coolify](https://coolify.io) - an open-source, self-hostable platform for deploying applications. This enterprise transformation preserves Coolify's deployment excellence while adding comprehensive multi-tenant and enterprise capabilities.

---

**For detailed development guidelines, see [CLAUDE.md](CLAUDE.md)**

**For Claude Code PM workflow, see [.claude/CCPM_README.md](.claude/CCPM_README.md)**
