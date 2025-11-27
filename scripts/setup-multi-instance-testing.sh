#!/bin/bash

# Multi-Instance Coolify Testing Setup
# This script sets up multiple Coolify instances for testing cross-branch communication

set -e

echo "🚀 Setting up Multi-Instance Coolify Testing Environment"
echo "=================================================="

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Create multi-instance docker-compose file
cat > docker-compose.multi-instance.yml << 'EOF'
version: '3.8'

networks:
  coolify-multi:
    driver: bridge

services:
  # Top Branch Instance (Primary - Port 8000)
  coolify-top:
    build: .
    container_name: coolify-top-branch
    ports:
      - "8000:80"
      - "5173:5173"  # Vite dev server
    environment:
      - APP_NAME=Coolify Top Branch
      - APP_URL=http://localhost:8000
      - BRANCH_TYPE=top_branch
      - BRANCH_ID=top-branch-001
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres-top
      - DB_PORT=5432
      - DB_DATABASE=coolify_top
      - DB_USERNAME=coolify
      - DB_PASSWORD=password
      - REDIS_HOST=redis-top
      - REDIS_PORT=6379
      - PUSHER_HOST=soketi-top
      - PUSHER_PORT=6001
      - PUSHER_APP_KEY=coolify-top
      - CROSS_BRANCH_API_KEY=top-branch-secure-key-123
    volumes:
      - .:/var/www/html
      - /var/run/docker.sock:/var/run/docker.sock
    networks:
      - coolify-multi
    depends_on:
      - postgres-top
      - redis-top
      - soketi-top

  postgres-top:
    image: postgres:15
    container_name: postgres-top-branch
    environment:
      POSTGRES_DB: coolify_top
      POSTGRES_USER: coolify
      POSTGRES_PASSWORD: password
    ports:
      - "5432:5432"
    volumes:
      - postgres_top_data:/var/lib/postgresql/data
    networks:
      - coolify-multi

  redis-top:
    image: redis:7-alpine
    container_name: redis-top-branch
    ports:
      - "6379:6379"
    volumes:
      - redis_top_data:/data
    networks:
      - coolify-multi

  soketi-top:
    image: quay.io/soketi/soketi:1.4-16-alpine
    container_name: soketi-top-branch
    environment:
      SOKETI_DEBUG: 1
      SOKETI_DEFAULT_APP_ID: coolify-top
      SOKETI_DEFAULT_APP_KEY: coolify-top
      SOKETI_DEFAULT_APP_SECRET: coolify-top-secret
    ports:
      - "6001:6001"
    networks:
      - coolify-multi

  # Master Branch Instance (Secondary - Port 8001)
  coolify-master:
    build: .
    container_name: coolify-master-branch
    ports:
      - "8001:80"
      - "5174:5173"  # Vite dev server (different port)
    environment:
      - APP_NAME=Coolify Master Branch
      - APP_URL=http://localhost:8001
      - BRANCH_TYPE=master_branch
      - BRANCH_ID=master-branch-001
      - PARENT_BRANCH_URL=http://coolify-top:80
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres-master
      - DB_PORT=5432
      - DB_DATABASE=coolify_master
      - DB_USERNAME=coolify
      - DB_PASSWORD=password
      - REDIS_HOST=redis-master
      - REDIS_PORT=6379
      - PUSHER_HOST=soketi-master
      - PUSHER_PORT=6001
      - PUSHER_APP_KEY=coolify-master
      - CROSS_BRANCH_API_KEY=master-branch-secure-key-456
    volumes:
      - .:/var/www/html
      - /var/run/docker.sock:/var/run/docker.sock
    networks:
      - coolify-multi
    depends_on:
      - postgres-master
      - redis-master
      - soketi-master
      - coolify-top

  postgres-master:
    image: postgres:15
    container_name: postgres-master-branch
    environment:
      POSTGRES_DB: coolify_master
      POSTGRES_USER: coolify
      POSTGRES_PASSWORD: password
    ports:
      - "5433:5432"
    volumes:
      - postgres_master_data:/var/lib/postgresql/data
    networks:
      - coolify-multi

  redis-master:
    image: redis:7-alpine
    container_name: redis-master-branch
    ports:
      - "6380:6379"
    volumes:
      - redis_master_data:/data
    networks:
      - coolify-multi

  soketi-master:
    image: quay.io/soketi/soketi:1.4-16-alpine
    container_name: soketi-master-branch
    environment:
      SOKETI_DEBUG: 1
      SOKETI_DEFAULT_APP_ID: coolify-master
      SOKETI_DEFAULT_APP_KEY: coolify-master
      SOKETI_DEFAULT_APP_SECRET: coolify-master-secret
    ports:
      - "6002:6001"
    networks:
      - coolify-multi

volumes:
  postgres_top_data:
  redis_top_data:
  postgres_master_data:
  redis_master_data:
EOF

echo "📝 Created docker-compose.multi-instance.yml"

# Create environment files for each instance
cat > .env.top-branch << 'EOF'
APP_NAME="Coolify Top Branch"
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres-top
DB_PORT=5432
DB_DATABASE=coolify_top
DB_USERNAME=coolify
DB_PASSWORD=password

BROADCAST_DRIVER=pusher
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=redis-top
REDIS_PASSWORD=null
REDIS_PORT=6379

PUSHER_APP_ID=coolify-top
PUSHER_APP_KEY=coolify-top
PUSHER_APP_SECRET=coolify-top-secret
PUSHER_HOST=soketi-top
PUSHER_PORT=6001
PUSHER_SCHEME=http

# Cross-branch configuration
BRANCH_TYPE=top_branch
BRANCH_ID=top-branch-001
CROSS_BRANCH_API_KEY=top-branch-secure-key-123
EOF

cat > .env.master-branch << 'EOF'
APP_NAME="Coolify Master Branch"
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost:8001

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres-master
DB_PORT=5432
DB_DATABASE=coolify_master
DB_USERNAME=coolify
DB_PASSWORD=password

BROADCAST_DRIVER=pusher
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=redis-master
REDIS_PASSWORD=null
REDIS_PORT=6379

PUSHER_APP_ID=coolify-master
PUSHER_APP_KEY=coolify-master
PUSHER_APP_SECRET=coolify-master-secret
PUSHER_HOST=soketi-master
PUSHER_PORT=6001
PUSHER_SCHEME=http

# Cross-branch configuration
BRANCH_TYPE=master_branch
BRANCH_ID=master-branch-001
PARENT_BRANCH_URL=http://coolify-top:80
CROSS_BRANCH_API_KEY=master-branch-secure-key-456
EOF

echo "📝 Created environment files for both instances"

# Create test data seeder for multi-instance
cat > database/seeders/MultiInstanceTestSeeder.php << 'EOF'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\User;
use App\Models\EnterpriseLicense;

class MultiInstanceTestSeeder extends Seeder
{
    public function run()
    {
        $branchType = env('BRANCH_TYPE', 'top_branch');
        
        if ($branchType === 'top_branch') {
            $this->seedTopBranch();
        } else {
            $this->seedMasterBranch();
        }
    }
    
    private function seedTopBranch()
    {
        // Create top branch organization
        $topOrg = Organization::factory()->topBranch()->create([
            'name' => 'Global Headquarters',
            'slug' => 'global-hq',
        ]);
        
        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Top Branch Admin',
            'email' => 'admin@topbranch.test',
            'password' => bcrypt('password'),
            'current_organization_id' => $topOrg->id,
        ]);
        
        $topOrg->users()->attach($admin->id, [
            'role' => 'owner',
            'permissions' => ['*'],
        ]);
        
        // Create enterprise license
        EnterpriseLicense::factory()->create([
            'organization_id' => $topOrg->id,
            'license_tier' => 'enterprise',
            'features' => [
                'cross_branch_communication',
                'multi_instance_management',
                'unlimited_organizations',
                'advanced_analytics',
            ],
        ]);
    }
    
    private function seedMasterBranch()
    {
        // Create master branch organization
        $masterOrg = Organization::factory()->masterBranch()->create([
            'name' => 'Regional Office',
            'slug' => 'regional-office',
        ]);
        
        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Master Branch Admin',
            'email' => 'admin@masterbranch.test',
            'password' => bcrypt('password'),
            'current_organization_id' => $masterOrg->id,
        ]);
        
        $masterOrg->users()->attach($admin->id, [
            'role' => 'admin',
            'permissions' => ['manage_servers', 'deploy_applications'],
        ]);
        
        // Create professional license
        EnterpriseLicense::factory()->create([
            'organization_id' => $masterOrg->id,
            'license_tier' => 'professional',
            'features' => [
                'infrastructure_provisioning',
                'domain_management',
                'payment_processing',
            ],
        ]);
    }
}
EOF

echo "📝 Created MultiInstanceTestSeeder"

# Function to wait for service to be ready
wait_for_service() {
    local url=$1
    local service_name=$2
    local max_attempts=30
    local attempt=1
    
    echo "⏳ Waiting for $service_name to be ready..."
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s -f "$url" > /dev/null 2>&1; then
            echo "✅ $service_name is ready!"
            return 0
        fi
        
        echo "   Attempt $attempt/$max_attempts - $service_name not ready yet..."
        sleep 5
        attempt=$((attempt + 1))
    done
    
    echo "❌ $service_name failed to start after $max_attempts attempts"
    return 1
}

# Start the multi-instance environment
echo "🐳 Starting multi-instance Docker environment..."
docker-compose -f docker-compose.multi-instance.yml down -v
docker-compose -f docker-compose.multi-instance.yml up -d --build

echo "⏳ Waiting for services to initialize..."
sleep 20

# Wait for databases to be ready
echo "🗄️ Waiting for databases..."
wait_for_service "http://localhost:5432" "Top Branch PostgreSQL" || exit 1
wait_for_service "http://localhost:5433" "Master Branch PostgreSQL" || exit 1

# Run migrations and seeders for both instances
echo "🔄 Running migrations and seeders..."

# Top branch
echo "   Setting up Top Branch database..."
docker exec coolify-top-branch php artisan migrate:fresh --force --env=.env.top-branch
docker exec coolify-top-branch php artisan db:seed --class=MultiInstanceTestSeeder --env=.env.top-branch

# Master branch  
echo "   Setting up Master Branch database..."
docker exec coolify-master-branch php artisan migrate:fresh --force --env=.env.master-branch
docker exec coolify-master-branch php artisan db:seed --class=MultiInstanceTestSeeder --env=.env.master-branch

# Wait for web services
wait_for_service "http://localhost:8000" "Top Branch Web Server" || exit 1
wait_for_service "http://localhost:8001" "Master Branch Web Server" || exit 1

echo ""
echo "🎉 Multi-Instance Coolify Testing Environment Ready!"
echo "=================================================="
echo ""
echo "🏢 Top Branch (Primary):     http://localhost:8000"
echo "   Admin: admin@topbranch.test / password"
echo "   Database: localhost:5432"
echo "   Redis: localhost:6379"
echo "   WebSocket: localhost:6001"
echo ""
echo "🏬 Master Branch (Secondary): http://localhost:8001"  
echo "   Admin: admin@masterbranch.test / password"
echo "   Database: localhost:5433"
echo "   Redis: localhost:6380"
echo "   WebSocket: localhost:6002"
echo ""
echo "🧪 Test Cross-Branch Communication:"
echo "   curl -X GET http://localhost:8000/api/health"
echo "   curl -X GET http://localhost:8001/api/health"
echo ""
echo "🛑 To stop: docker-compose -f docker-compose.multi-instance.yml down"
echo "🗑️ To clean: docker-compose -f docker-compose.multi-instance.yml down -v"