# Cross-Branch Communication Architecture

## Overview

This document outlines the design for cross-branch communication in the Coolify Enterprise platform, enabling multiple Coolify instances to communicate and share resources across different domains and infrastructure.

## Architecture Components

### 1. Branch Registry Service

Each branch maintains a registry of connected branches:

```php
// New table: branch_registry
CREATE TABLE branch_registry (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id),
    branch_name VARCHAR(255) NOT NULL,
    branch_url VARCHAR(255) NOT NULL,
    branch_type VARCHAR(50) NOT NULL, -- top_branch, master_branch
    api_key VARCHAR(255) NOT NULL, -- encrypted
    ssl_certificate TEXT,
    is_active BOOLEAN DEFAULT true,
    last_ping_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(organization_id, branch_name)
);
```

### 2. Cross-Branch API Gateway

```php
interface CrossBranchServiceInterface
{
    public function registerBranch(string $branchUrl, string $apiKey, string $branchType): BranchRegistration;
    public function communicateWithBranch(string $branchId, string $endpoint, array $data): array;
    public function syncOrganizationData(string $branchId, string $organizationId): bool;
    public function validateCrossBranchLicense(string $licenseKey, string $domain): LicenseValidationResult;
}

class CrossBranchService implements CrossBranchServiceInterface
{
    public function communicateWithBranch(string $branchId, string $endpoint, array $data): array
    {
        $branch = BranchRegistry::findOrFail($branchId);
        
        $client = new GuzzleHttp\Client([
            'base_uri' => $branch->branch_url,
            'timeout' => 30,
            'verify' => !app()->environment('local'), // Skip SSL in local
        ]);

        try {
            $response = $client->post("/api/v1/cross-branch/{$endpoint}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . decrypt($branch->api_key),
                    'Content-Type' => 'application/json',
                    'X-Branch-Origin' => config('app.url'),
                ],
                'json' => $data,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new CrossBranchCommunicationException(
                "Failed to communicate with branch {$branch->branch_name}: " . $e->getMessage()
            );
        }
    }

    public function syncOrganizationData(string $branchId, string $organizationId): bool
    {
        $organization = Organization::findOrFail($organizationId);
        
        return $this->communicateWithBranch($branchId, 'sync-organization', [
            'organization' => $organization->toArray(),
            'users' => $organization->users()->get()->toArray(),
            'license' => $organization->activeLicense?->toArray(),
        ]);
    }
}
```

### 3. Cross-Branch Authentication

```php
class CrossBranchAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        $branchOrigin = $request->header('X-Branch-Origin');
        $token = $request->bearerToken();

        if (!$branchOrigin || !$token) {
            return response()->json(['error' => 'Cross-branch authentication required'], 401);
        }

        // Validate the requesting branch
        $branch = BranchRegistry::where('branch_url', $branchOrigin)
            ->where('is_active', true)
            ->first();

        if (!$branch || !hash_equals(decrypt($branch->api_key), $token)) {
            return response()->json(['error' => 'Invalid branch credentials'], 403);
        }

        // Add branch context to request
        $request->attributes->set('source_branch', $branch);
        
        return $next($request);
    }
}
```

### 4. Cross-Branch Routes

```php
// routes/cross-branch.php
Route::middleware(['cross-branch-auth'])->prefix('api/v1/cross-branch')->group(function () {
    Route::post('sync-organization', [CrossBranchController::class, 'syncOrganization']);
    Route::post('validate-license', [CrossBranchController::class, 'validateLicense']);
    Route::post('share-resource', [CrossBranchController::class, 'shareResource']);
    Route::get('health', [CrossBranchController::class, 'health']);
    Route::post('user-authentication', [CrossBranchController::class, 'authenticateUser']);
});
```

## Local Testing Setup

### 1. Multi-Container Development Environment

Create a new docker-compose file for multi-instance testing:

```yaml
# docker-compose.multi-branch.yml
version: '3.8'

services:
  # Top Branch Instance (Port 8000)
  coolify-top-branch:
    build: .
    ports:
      - "8000:80"
    environment:
      - APP_NAME="Coolify Top Branch"
      - APP_URL=http://localhost:8000
      - BRANCH_TYPE=top_branch
      - DB_HOST=postgres-top
      - REDIS_HOST=redis-top
    volumes:
      - .:/var/www/html
    depends_on:
      - postgres-top
      - redis-top

  postgres-top:
    image: postgres:15
    environment:
      POSTGRES_DB: coolify_top
      POSTGRES_USER: coolify
      POSTGRES_PASSWORD: password
    ports:
      - "5432:5432"
    volumes:
      - postgres_top_data:/var/lib/postgresql/data

  redis-top:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_top_data:/data

  # Master Branch Instance (Port 8001)
  coolify-master-branch:
    build: .
    ports:
      - "8001:80"
    environment:
      - APP_NAME="Coolify Master Branch"
      - APP_URL=http://localhost:8001
      - BRANCH_TYPE=master_branch
      - DB_HOST=postgres-master
      - REDIS_HOST=redis-master
      - PARENT_BRANCH_URL=http://coolify-top-branch
    volumes:
      - .:/var/www/html
    depends_on:
      - postgres-master
      - redis-master
      - coolify-top-branch

  postgres-master:
    image: postgres:15
    environment:
      POSTGRES_DB: coolify_master
      POSTGRES_USER: coolify
      POSTGRES_PASSWORD: password
    ports:
      - "5433:5432"
    volumes:
      - postgres_master_data:/var/lib/postgresql/data

  redis-master:
    image: redis:7-alpine
    ports:
      - "6380:6379"
    volumes:
      - redis_master_data:/data

volumes:
  postgres_top_data:
  redis_top_data:
  postgres_master_data:
  redis_master_data:
```

### 2. Branch Registration Script

```bash
#!/bin/bash
# scripts/setup-cross-branch-testing.sh

echo "Setting up cross-branch communication testing..."

# Start both instances
docker-compose -f docker-compose.multi-branch.yml up -d

# Wait for services to be ready
sleep 30

# Register master branch with top branch
curl -X POST http://localhost:8000/api/v1/cross-branch/register-branch \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer top-branch-api-key" \
  -d '{
    "branch_name": "Master Branch Test",
    "branch_url": "http://localhost:8001",
    "branch_type": "master_branch",
    "api_key": "master-branch-api-key"
  }'

# Test communication
curl -X GET http://localhost:8000/api/v1/cross-branch/test-communication \
  -H "Authorization: Bearer top-branch-api-key"

echo "Cross-branch setup complete!"
echo "Top Branch: http://localhost:8000"
echo "Master Branch: http://localhost:8001"
```

### 3. Testing Cross-Branch Features

```php
// tests/Feature/CrossBranchCommunicationTest.php
class CrossBranchCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_registration()
    {
        $topBranch = Organization::factory()->topBranch()->create();
        
        $response = $this->postJson('/api/v1/cross-branch/register-branch', [
            'branch_name' => 'Test Master Branch',
            'branch_url' => 'http://localhost:8001',
            'branch_type' => 'master_branch',
            'api_key' => 'test-api-key',
        ], [
            'Authorization' => 'Bearer top-branch-token',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('branch_registry', [
            'branch_name' => 'Test Master Branch',
            'branch_url' => 'http://localhost:8001',
        ]);
    }

    public function test_cross_branch_organization_sync()
    {
        // Mock HTTP client for testing
        Http::fake([
            'localhost:8001/api/v1/cross-branch/sync-organization' => Http::response([
                'success' => true,
                'message' => 'Organization synced successfully',
            ], 200),
        ]);

        $service = new CrossBranchService();
        $result = $service->syncOrganizationData('branch-id', 'org-id');

        $this->assertTrue($result);
    }
}
```

## Implementation Priority

This feature should be implemented as **Task 13: Cross-Branch Communication** after the core enterprise features are complete:

```markdown
- [ ] 13. Cross-Branch Communication and Multi-Instance Support
  - Implement branch registry and cross-branch API gateway
  - Create federated authentication across branch instances  
  - Add cross-branch resource sharing and management
  - Integrate distributed licensing validation
  - Build multi-instance monitoring and reporting
  - _Requirements: Multi-instance deployment, cross-branch communication_
```

## Benefits of This Approach

1. **True Enterprise Scale**: Support for geographically distributed branches
2. **Regulatory Compliance**: Data can stay in specific regions/countries
3. **Performance**: Reduced latency for regional users
4. **Resilience**: No single point of failure
5. **Flexibility**: Different branches can have different configurations

This architecture would enable scenarios like:
- A top branch in the US managing master branches in EU and Asia
- Each master branch serving local customers with data sovereignty
- Centralized licensing and billing through the top branch
- Cross-branch user authentication and resource sharing