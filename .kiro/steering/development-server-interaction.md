---
inclusion: always
---
# Coolify Development Server Interaction Guide

## Overview

This guide provides comprehensive instructions for interacting with the **production-like Coolify development environment** that includes hot-reloading capabilities, full service stack, and real-time development features. The setup combines the robustness of a production environment with the convenience of development tooling.

## Development Environment Architecture

### Current Environment Setup
The development environment runs a **full production-like stack** using Docker containers with the following services:

- **🌐 Coolify Application**: Main Laravel application (http://localhost:8000)
- **⚡ Vite Dev Server**: Frontend hot-reload server (http://localhost:5173)
- **📡 Soketi WebSocket**: Real-time features (http://localhost:6001)
- **🗄️ PostgreSQL 15**: Primary database (localhost:5432)
- **🗂️ Redis 7**: Caching and sessions (localhost:6379)
- **📧 Mailpit**: Email testing (http://localhost:8025)
- **💾 MinIO**: S3-compatible storage (http://localhost:9001)
- **🧪 Testing Host**: SSH testing environment

### Service Configuration Files
- **[docker-compose.dev-full.yml](mdc:docker-compose.dev-full.yml)** - Production-like development stack
- **[dev.sh](mdc:dev.sh)** - Development environment management script
- **[watch-backend.sh](mdc:watch-backend.sh)** - Backend file watcher for auto-reload
- **[.env](mdc:.env)** - Environment configuration with Docker network settings

## Development Server Management

### Primary Management Script: `dev.sh`

The `dev.sh` script is the **central command interface** for all development server operations:

```bash
# Start the complete development environment
./dev.sh start

# View all available commands
./dev.sh help

# Check status of all services
./dev.sh status

# View logs for all services
./dev.sh logs

# View logs for specific service
./dev.sh logs coolify
./dev.sh logs vite
./dev.sh logs soketi
```

### Available Commands

#### Environment Control
```bash
./dev.sh start           # Start all services
./dev.sh stop            # Stop all services  
./dev.sh restart         # Restart all services
./dev.sh status          # Show services status
```

#### Development Tools
```bash
./dev.sh watch          # Start backend file watcher for auto-reload
./dev.sh shell           # Open shell in coolify container
./dev.sh db              # Connect to PostgreSQL database
./dev.sh logs [service]  # View logs (all or specific service)
```

#### Maintenance Operations
```bash
./dev.sh build          # Rebuild Docker images
./dev.sh clean           # Stop and clean up everything
```

## Hot-Reloading Development Workflow

### Frontend Hot-Reloading (Automatic)
- **Automatic**: Vite dev server provides instant hot-reloading
- **Files watched**: CSS, JavaScript, Vue components, Blade templates
- **Access**: Frontend changes appear immediately in browser
- **Process**: No manual intervention required

### Backend Hot-Reloading (Command-Triggered)
```bash
# In a separate terminal, start the file watcher
./dev.sh watch

# The watcher monitors these file types:
# - *.php (PHP files)
# - *.blade.php (Blade templates)
# - *.json (Configuration files)
# - *.yaml/*.yml (YAML configurations)
# - .env (Environment variables)

# Watched directories:
# - app/ (Application logic)
# - routes/ (Route definitions)
# - config/ (Configuration files)
# - resources/views/ (Blade templates)
# - database/ (Migrations, seeders)
# - bootstrap/ (Application bootstrap)
```

**File Watcher Behavior**:
- Detects file changes in real-time
- Automatically restarts the Coolify container
- Includes debouncing to prevent rapid restarts
- Displays file change notifications
- Preserves database state and volumes

## Authentication & Access

### Default Credentials
- **Primary Admin Account**:
  - **Email**: `test@example.com`
  - **Password**: `password`
  - **Role**: Root/Admin user with full privileges

- **Additional Test Accounts**:
  - **Email**: `test2@example.com` / **Password**: `password` (Normal user in root team)
  - **Email**: `test3@example.com` / **Password**: `password` (Normal user not in root team)

### Access URLs
- **Main Application**: http://localhost:8000
- **Email Testing**: http://localhost:8025 (Mailpit dashboard)
- **S3 Storage**: http://localhost:9001 (MinIO console)

## Database Development

### Database Connection
```bash
# Connect to PostgreSQL via development script
./dev.sh db

# Or connect directly
psql -h localhost -p 5432 -U coolify -d coolify

# Connection from host machine
# Host: localhost
# Port: 5432  
# Database: coolify
# Username: coolify
# Password: password
```

### Database Operations
```bash
# Run migrations (inside container)
./dev.sh shell
php artisan migrate

# Seed development data
php artisan db:seed

# Create new migration
php artisan make:migration create_new_table

# Reset database
php artisan migrate:fresh --seed
```

## Development Patterns

### Container-based Development
- **Code Location**: All source code is mounted as volumes
- **Hot-reloading**: File changes trigger automatic reloads
- **Database Persistence**: Data survives container restarts
- **Log Access**: Real-time log streaming via `./dev.sh logs`

### Service Dependencies
```yaml
# Service startup order (automatic)
1. PostgreSQL (database)
2. Redis (caching)
3. Soketi (websockets) 
4. Coolify (main app)
5. Vite (frontend dev server)
6. Supporting services (MailPit, MinIO, Testing Host)
```

## Debugging & Troubleshooting

### Log Investigation
```bash
# View all service logs
./dev.sh logs

# Focus on specific service
./dev.sh logs coolify    # Application logs
./dev.sh logs postgres   # Database logs  
./dev.sh logs redis      # Cache logs
./dev.sh logs vite       # Frontend build logs
./dev.sh logs soketi     # WebSocket logs
```

### Container Debugging
```bash
# Open shell in main application container
./dev.sh shell

# Check service health
./dev.sh status

# Restart specific service
docker-compose -f docker-compose.dev-full.yml restart coolify
```

### Common Issues & Solutions

#### Port Conflicts
```bash
# Check what's using a port
lsof -i :8000

# Stop conflicting processes
./dev.sh stop
```

#### Database Connection Issues
```bash
# Verify PostgreSQL is running
./dev.sh status | grep postgres

# Check database connectivity
./dev.sh db
\l  # List databases
\q  # Quit
```

#### Frontend Asset Issues
```bash
# Rebuild assets
./dev.sh shell
npm run build

# Or restart Vite service
docker-compose -f docker-compose.dev-full.yml restart vite
```

## Performance Optimization

### Development Performance
- **Volume Caching**: Uses cached volume mounts for better performance
- **Selective Restarts**: File watcher only restarts affected services
- **Asset Streaming**: Vite provides fast hot-module replacement
- **Database Persistence**: Avoids migration reruns on restart

### Resource Monitoring
```bash
# Check Docker resource usage
docker stats

# Monitor specific container
docker stats topgun-coolify-1

# View container processes
docker-compose -f docker-compose.dev-full.yml top
```

## Code Quality Integration

### Code Style & Analysis
```bash
# Access container for code quality checks
./dev.sh shell

# PHP code style (Laravel Pint)
./vendor/bin/pint

# Static analysis (PHPStan)  
./vendor/bin/phpstan analyse

# Run tests
./vendor/bin/pest
```

### Pre-commit Workflow
```bash
# Before committing changes
./dev.sh shell

# Run all quality checks
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/pest

# Frontend checks (if applicable)
npm run lint
npm run test
```

## Environment Configuration

### Key Environment Variables
```bash
# Database Configuration
DB_HOST=postgres          # Docker service name
DB_DATABASE=coolify
DB_USERNAME=coolify
DB_PASSWORD=password

# Redis Configuration  
REDIS_HOST=redis          # Docker service name
REDIS_PORT=6379

# WebSocket Configuration
PUSHER_HOST=soketi        # Docker service name
PUSHER_PORT=6001
PUSHER_APP_KEY=coolify
```

### Network Configuration
- **Container Network**: `coolify` (internal Docker network)
- **Host Access**: Services exposed on localhost with port mapping
- **Inter-service Communication**: Uses Docker service names

## API Development & Testing

### API Access
- **Base URL**: http://localhost:8000/api/v1
- **Authentication**: Sanctum tokens or session-based
- **Documentation**: Available at `/docs` or via OpenAPI spec

### Testing API Endpoints
```bash
# Test authentication
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Test authenticated endpoints
curl -X GET http://localhost:8000/api/v1/applications \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## WebSocket Development

### Real-time Features
- **WebSocket Server**: Soketi running on port 6001
- **Laravel Echo**: Frontend WebSocket client
- **Broadcasting**: Real-time deployment updates, notifications

### Testing WebSocket Connections
```bash
# Check Soketi status
curl http://localhost:6001

# Monitor WebSocket events in browser console
# or use WebSocket testing tools
```

## Backup & Recovery

### Data Persistence
- **Database Data**: Stored in Docker volume `topgun_dev_postgres_data`
- **Redis Data**: Stored in Docker volume `topgun_dev_redis_data`  
- **File Uploads**: Stored in Docker volume `topgun_dev_backups_data`

### Backup Operations
```bash
# Backup database
./dev.sh db
pg_dump coolify > backup.sql

# Restore database
./dev.sh db
psql coolify < backup.sql
```

## Security Considerations

### Development Security
- **Exposed Ports**: Services only exposed on localhost
- **Default Credentials**: Use only for development
- **SSL/TLS**: Not required in development environment
- **Network Isolation**: Docker network provides service isolation

### Production Preparation
- **Environment Variables**: Review and secure for production
- **Credentials**: Change all default passwords
- **Network Configuration**: Configure proper firewall rules
- **SSL Certificates**: Implement proper TLS configuration

## Migration to Production

### Key Differences
- **Environment Variables**: Production values in `.env.production`
- **Docker Compose**: Use `docker-compose.prod.yml`
- **Database**: External PostgreSQL instance
- **Storage**: External S3/MinIO configuration
- **SSL/TLS**: Proper certificate configuration

### Preparation Steps
```bash
# Review production environment file
cp .env.development.example .env.production

# Build production images
docker-compose -f docker-compose.prod.yml build

# Run production migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

## Best Practices

### Development Workflow
1. **Start Environment**: Always use `./dev.sh start`
2. **Enable Watching**: Run `./dev.sh watch` in separate terminal
3. **Check Status**: Regularly verify service health with `./dev.sh status`
4. **View Logs**: Monitor logs during development with `./dev.sh logs`
5. **Clean Shutdown**: Use `./dev.sh stop` when finished

### Code Development
1. **Edit Files**: Make changes directly in mounted source code
2. **Test Changes**: Verify functionality in browser/API
3. **Check Logs**: Monitor application logs for errors
4. **Database Changes**: Run migrations as needed
5. **Quality Checks**: Run code quality tools before commits

### Troubleshooting Approach
1. **Check Status**: Start with `./dev.sh status`
2. **Review Logs**: Use `./dev.sh logs [service]`
3. **Restart Services**: Try `./dev.sh restart`
4. **Clean Restart**: Use `./dev.sh stop` then `./dev.sh start`
5. **Rebuild Images**: Use `./dev.sh build` for major issues

This comprehensive guide provides all necessary information for effective development with the production-like Coolify development environment, enabling efficient development with professional-grade tooling and hot-reloading capabilities.
