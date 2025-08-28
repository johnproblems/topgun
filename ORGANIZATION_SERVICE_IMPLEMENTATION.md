# Organization Management Service Implementation

## Overview

Task 1.4 "Create Organization Management Service" has been successfully implemented. This service provides comprehensive organization hierarchy management, user role management, permission checking, and organization context switching for the Coolify Enterprise transformation.

## Implemented Components

### 1. Core Service (`app/Services/OrganizationService.php`)

The `OrganizationService` implements the `OrganizationServiceInterface` and provides:

#### Organization Management
- `createOrganization()` - Create new organizations with hierarchy validation
- `updateOrganization()` - Update existing organizations with validation
- `moveOrganization()` - Move organizations in hierarchy with circular dependency prevention
- `deleteOrganization()` - Safe deletion with resource cleanup

#### User Management
- `attachUserToOrganization()` - Add users to organizations with role assignment
- `detachUserFromOrganization()` - Remove users with last-owner protection
- `updateUserRole()` - Update user roles and permissions
- `switchUserOrganization()` - Switch user's current organization context

#### Permission & Access Control
- `canUserPerformAction()` - Role-based permission checking with license validation
- `getUserOrganizations()` - Get organizations accessible by a user (cached)

#### Hierarchy & Analytics
- `getOrganizationHierarchy()` - Build complete organization tree structure
- `getOrganizationUsage()` - Get usage statistics and metrics

### 2. Service Interface (`app/Contracts/OrganizationServiceInterface.php`)

Defines the contract for organization management operations, ensuring consistent API across implementations.

### 3. Helper Classes

#### OrganizationContext (`app/Helpers/OrganizationContext.php`)
Static helper class providing convenient access to:
- Current organization context
- Permission checking
- Feature availability
- User role information
- Organization switching

#### EnsureOrganizationContext Middleware (`app/Http/Middleware/EnsureOrganizationContext.php`)
Middleware that:
- Ensures authenticated users have an organization context
- Validates user access to current organization
- Automatically switches to accessible organization if needed

### 4. Livewire Component (`app/Livewire/Organization/OrganizationManager.php`)

Full-featured organization management interface with:
- Organization creation and editing
- User management and role assignment
- Organization switching
- Hierarchy visualization
- Permission-based UI controls

### 5. Database Factories

#### OrganizationFactory (`database/factories/OrganizationFactory.php`)
- Supports all hierarchy types
- Parent-child relationship creation
- State methods for different organization types

#### EnterpriseLicenseFactory (`database/factories/EnterpriseLicenseFactory.php`)
- License creation with features and limits
- Different license types (trial, subscription, perpetual)
- Domain authorization support

### 6. Validation & Testing

#### Unit Tests (`tests/Unit/OrganizationServiceUnitTest.php`)
Tests core service logic without database dependencies:
- Hierarchy validation rules
- Role permission checking
- Circular dependency detection
- Data validation

#### Validation Command (`app/Console/Commands/ValidateOrganizationService.php`)
Comprehensive validation of:
- Service binding and interface implementation
- Method availability
- Model relationships
- Helper class existence
- Hierarchy rule validation

## Key Features Implemented

### 1. Hierarchical Organization Structure
- **Top Branch** → **Master Branch** → **Sub User** → **End User**
- Strict hierarchy validation prevents invalid parent-child relationships
- Circular dependency prevention in organization moves
- Automatic hierarchy level management

### 2. Role-Based Access Control
- **Owner**: Full access to everything
- **Admin**: Most actions except organization deletion and billing
- **Member**: Limited to application and server management
- **Viewer**: Read-only access
- Custom permissions support for fine-grained control

### 3. License Integration
- Actions validated against organization's active license
- Feature flags control access to enterprise functionality
- Usage limits enforced (users, servers, domains)
- Graceful degradation for expired/invalid licenses

### 4. Caching & Performance
- User organizations cached for 30 minutes
- Permission checks cached for 15 minutes
- Organization hierarchy cached for 1 hour
- Usage statistics cached for 5 minutes
- Automatic cache invalidation on updates

### 5. Data Integrity & Validation
- Prevents removing last owner from organization
- Validates hierarchy creation rules
- Enforces license limits on user attachment
- Slug uniqueness validation
- Comprehensive error handling

### 6. Context Management
- User can switch between accessible organizations
- Current organization context maintained in session
- Middleware ensures valid organization context
- Helper methods for easy context access

## Integration Points

### With Existing Coolify Models
- **User Model**: Extended with organization relationships and context methods
- **Server Model**: Organization ownership and permission checking
- **Application Model**: Inherited organization context through servers

### With Enterprise Features
- **Licensing System**: Permission validation and feature checking
- **White-Label Branding**: Organization-specific branding context
- **Payment Processing**: Organization-based billing and limits
- **Cloud Provisioning**: Organization resource ownership

### Service Provider Registration
The service is properly registered in `AppServiceProvider` with interface binding:

```php
$this->app->bind(
    \App\Contracts\OrganizationServiceInterface::class,
    \App\Services\OrganizationService::class
);
```

## Usage Examples

### Creating Organizations
```php
$organizationService = app(OrganizationServiceInterface::class);

$topBranch = $organizationService->createOrganization([
    'name' => 'Acme Corporation',
    'hierarchy_type' => 'top_branch',
]);

$masterBranch = $organizationService->createOrganization([
    'name' => 'Hosting Division',
    'hierarchy_type' => 'master_branch',
], $topBranch);
```

### Managing Users
```php
$organizationService->attachUserToOrganization($organization, $user, 'admin');
$organizationService->updateUserRole($organization, $user, 'member', ['deploy_applications']);
$organizationService->switchUserOrganization($user, $organization);
```

### Permission Checking
```php
// Using the service directly
$canDeploy = $organizationService->canUserPerformAction($user, $organization, 'deploy_applications');

// Using the helper
$canDeploy = OrganizationContext::can('deploy_applications');
```

### Getting Organization Data
```php
$hierarchy = $organizationService->getOrganizationHierarchy($organization);
$usage = $organizationService->getOrganizationUsage($organization);
$userOrgs = $organizationService->getUserOrganizations($user);
```

## Validation Results

The implementation has been validated with the following results:
- ✅ Service binding works correctly
- ✅ Implements OrganizationServiceInterface completely
- ✅ All interface methods implemented
- ✅ Protected helper methods available
- ✅ Model relationships properly defined
- ✅ Helper classes created and accessible
- ✅ Livewire component available
- ✅ Hierarchy validation rules working

## Requirements Satisfied

This implementation satisfies all requirements from task 1.4:

1. ✅ **Implement OrganizationService for hierarchy management**
   - Complete service with all hierarchy operations
   - Validation of parent-child relationships
   - Circular dependency prevention

2. ✅ **Add methods for creating, updating, and managing organization relationships**
   - CRUD operations for organizations
   - User-organization relationship management
   - Organization moving and restructuring

3. ✅ **Implement permission checking and role-based access control**
   - Comprehensive RBAC system
   - License-based feature validation
   - Cached permission checking for performance

4. ✅ **Create organization switching and context management**
   - User organization context switching
   - Middleware for context validation
   - Helper class for easy context access

The OrganizationService is now ready to support the enterprise transformation of Coolify, providing a solid foundation for multi-tenant organization management with proper hierarchy, permissions, and context handling.