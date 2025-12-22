#!/bin/bash

# Dev Container Start Script
# This script runs every time the dev container starts

set -e

echo "🔄 Starting Coolify Enterprise Development Environment..."

# Navigate to workspace
cd /workspace

# Wait for services to be ready
echo "⏳ Waiting for services..."

# Check PostgreSQL
if pg_isready -h postgres -U coolify > /dev/null 2>&1; then
    echo "✅ PostgreSQL is ready"
else
    echo "⚠️  PostgreSQL is not ready yet. It may take a moment..."
fi

# Check Redis
if redis-cli -h redis ping > /dev/null 2>&1; then
    echo "✅ Redis is ready"
else
    echo "⚠️  Redis is not ready yet. It may take a moment..."
fi

# Clear Laravel caches for fresh start
echo "🧹 Clearing Laravel caches..."
php artisan config:clear > /dev/null 2>&1 || true
php artisan cache:clear > /dev/null 2>&1 || true
php artisan view:clear > /dev/null 2>&1 || true

# Ensure storage link exists
php artisan storage:link > /dev/null 2>&1 || true

echo ""
echo "✅ Development environment is ready!"
echo ""
echo "🚀 Quick Start:"
echo "   • php artisan serve           - Start application"
echo "   • npm run dev                 - Start frontend dev server"
echo "   • php artisan queue:work      - Start queue worker"
echo "   • php artisan reverb:start    - Start WebSocket server"
echo ""
echo "   Or use: ./dev.sh start        - Start all services with Docker Compose"
echo ""
