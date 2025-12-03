# License Validation Middleware

This document describes the license validation middleware implementation for Coolify's enterprise transformation.

## Overview

The license validation middleware system provides comprehensive license checking for critical routes, API endpoints, and server provisioning workflows. It implements graceful degradation for expired licenses and ensures proper feature-based access control.

## Middleware Components

### 1. ValidateLicense (`license`)

**Purpose**: General license validation for web routes and basic feature checking.

**Usage**:
```php
Route::get('/servers', ServerIndex::class)->middleware(['license']);
Route::get('/advanced-feature', SomeController::class)->middleware(['license:advanced_feature']);
```

**Features**:
- Validates active license for organization
- Checks feature-specific permissions
- Implements graceful degradation during grace period
- Redirects to appropriate license pages for web routes
- Skips validation in development mode

### 2. ApiLicenseValidation (`api.license`)

**Purpose**: API-specific license validation with detailed JSON responses and rate limiting.

**Usage**:
```php
Route::group(['middleware' => ['auth:sanctum', 'api.license']], function () {
    Route::get('/servers', [ServersController::class, 'index']);
});

// With specific features
Route::post('/servers', [ServersController::class, 'create'])
    ->middleware(['api.license:server_provisioning']);
```

**Features**:
- JSON error responses for API clients
- License tier-based rate limiting
- Detailed license headers in responses
- Grace period handling with warnings
- Feature-specific validation

### 3. ServerProvisioningLicense (`server.provision`)

**Purpose**: Specialized middleware for server and infrastructure provisioning operations.

**Usage**:
```php
Route::post('/servers', [ServersController::class, 'create'])
    ->middleware(['server.provision']);

Route::prefix('infrastructure')->middleware(['server.provision'])->group(function () {
    // Infrastructure provisioning routes
});
```

**Features**:
- Validates server provisioning capabilities
- Checks server count limits
- Validates cloud provider limits
- Blocks provisioning for expired licenses (no grace period)
- Audit logging for provisioning attempts

## Applied Routes

### API Routes (routes/api.php)

All API routes under `/api/v1/` now include `api.license` middleware:

```php
Route::group([
    'middleware' => ['auth:sanctum', ApiAllowed::class, 'api.sensitive', 'api.license'],
    'prefix' => 'v1',
], function () {
    // All API routes now have license validation
});
```

**Specific feature requirements**:
- Server creation/management: `server_provisioning`
- Application deployment: `server_provisioning`
- Infrastructure operations: `infrastructure_provisioning`, `terraform_integration`

### Web Routes (routes/web.php)

Server management routes now include license validation:

```php
// Server listing requires basic license
Route::get('/servers', ServerIndex::class)->middleware(['license']);

// Server management requires server provisioning feature
Route::prefix('server/{server_uuid}')->middleware(['license:server_provisioning'])->group(function () {
    // Server management routes
});

// Server deletion requires full provisioning license
Route::get('/danger', DeleteServer::class)->middleware(['server.provision']);
```

## License Features

The system recognizes these license features:

- `server_provisioning` - Basic server management
- `infrastructure_provisioning` - Advanced infrastructure management
- `terraform_integration` - Terraform-based provisioning
- `payment_processing` - Payment and billing features
- `domain_management` - Domain and DNS management
- `white_label_branding` - Custom branding
- `api_access` - API endpoint access
- `bulk_operations` - Bulk management operations
- `advanced_monitoring` - Enhanced monitoring features
- `multi_cloud_support` - Multiple cloud provider support
- `sso_integration` - Single sign-on integration
- `audit_logging` - Comprehensive audit logs
- `backup_management` - Backup and restore features
- `ssl_management` - SSL certificate management
- `load_balancing` - Load balancer management

## License Tiers and Limits

### Basic Tier
- Max servers: 5
- Max applications: 10
- Max domains: 3
- Max users: 3
- Max cloud providers: 1
- Features: `server_provisioning`, `api_access`

### Professional Tier
- Max servers: 25
- Max applications: 100
- Max domains: 25
- Max users: 10
- Max cloud providers: 3
- Features: All basic features plus `infrastructure_provisioning`, `terraform_integration`, `payment_processing`, `domain_management`, `bulk_operations`, `ssl_management`

### Enterprise Tier
- Unlimited resources
- All features available
- Priority support

## Grace Period Handling

When a license expires, the system provides a 7-day grace period:

**Allowed during grace period**:
- Read operations (viewing servers, applications, etc.)
- Basic monitoring and logs
- User management

**Blocked during grace period**:
- Server provisioning
- Infrastructure provisioning
- Payment processing
- Domain management
- Bulk operations

## Error Responses

### API Error Response Format

```json
{
    "success": false,
    "message": "License validation failed",
    "error_code": "LICENSE_EXPIRED",
    "license_status": "expired",
    "license_tier": "professional",
    "expired_at": "2024-01-15T10:30:00Z",
    "days_expired": 5,
    "required_features": ["server_provisioning"],
    "violations": [
        {
            "type": "expiration",
            "message": "License expired 5 days ago"
        }
    ]
}
```

### Error Codes

- `NO_ORGANIZATION_CONTEXT` - User not associated with organization
- `NO_VALID_LICENSE` - No active license found
- `LICENSE_EXPIRED` - License has expired
- `LICENSE_REVOKED` - License has been revoked
- `LICENSE_SUSPENDED` - License is suspended
- `DOMAIN_NOT_AUTHORIZED` - Domain not authorized for license
- `USAGE_LIMITS_EXCEEDED` - Resource limits exceeded
- `INSUFFICIENT_LICENSE_FEATURES` - Required features not available
- `LICENSE_GRACE_PERIOD_RESTRICTION` - Feature restricted during grace period
- `FEATURE_NOT_LICENSED` - Specific feature not included in license
- `SERVER_LIMIT_EXCEEDED` - Server count limit reached
- `CLOUD_PROVIDER_LIMIT_EXCEEDED` - Cloud provider limit reached

## Rate Limiting

API rate limits are applied based on license tier:

- **Basic**: 1,000 requests per hour
- **Professional**: 5,000 requests per hour  
- **Enterprise**: 10,000 requests per hour

Rate limits are applied per organization and IP address combination.

## Response Headers

API responses include license information headers:

```
X-License-Tier: professional
X-License-Status: active
X-License-Expires: 2024-12-31T23:59:59Z
X-License-Days-Remaining: 45
X-Usage-servers: 75%
X-Usage-applications: 45%
```

## Configuration

License validation is configured in `config/licensing.php`:

```php
return [
    'grace_period_days' => 7,
    'cache_ttl' => 300, // 5 minutes
    'rate_limits' => [
        'basic' => ['max_attempts' => 1000, 'decay_minutes' => 60],
        'professional' => ['max_attempts' => 5000, 'decay_minutes' => 60],
        'enterprise' => ['max_attempts' => 10000, 'decay_minutes' => 60],
    ],
    // ... additional configuration
];
```

## License Pages

The middleware redirects to appropriate license management pages:

- `/license/required` - When no valid license is found
- `/license/invalid` - When license validation fails
- `/license/upgrade` - When required features are missing
- `/organization/setup` - When no organization context is available

## Testing

The middleware includes comprehensive test coverage:

- Unit tests for middleware logic
- Feature tests for route protection
- Integration tests for license validation
- Grace period behavior tests
- Rate limiting tests

## Implementation Notes

1. **Development Mode**: All license validation is skipped when `isDev()` returns true
2. **Caching**: License validation results are cached for 5 minutes to improve performance
3. **Audit Logging**: All license validation failures and provisioning attempts are logged
4. **Graceful Degradation**: The system continues to function with limited capabilities during grace periods
5. **Backward Compatibility**: The middleware integrates with existing authentication and authorization systems

## Security Considerations

- License keys are validated against authorized domains
- Rate limiting prevents abuse
- Audit logging tracks all license-related activities
- Graceful degradation prevents complete service disruption
- Feature-based access control ensures proper authorization

This middleware system provides comprehensive license validation while maintaining system usability and security.