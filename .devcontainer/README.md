# Dev Container Configuration

This directory contains the complete development container configuration for the **Coolify Enterprise Transformation** project. The dev container provides a fully configured development environment with all necessary tools, dependencies, and services.

## 🎯 What's Included

### Core Technologies
- **PHP 8.4** - Latest PHP version with all required extensions
- **Node.js 20 LTS** - Modern JavaScript runtime
- **Composer** - PHP dependency manager
- **npm/yarn/pnpm** - Node.js package managers
- **PostgreSQL 15** - Primary database
- **Redis 7** - Caching and queues
- **Docker-in-Docker** - Container management
- **Terraform** - Infrastructure as Code

### AI Assistant CLIs
- **GitHub Copilot CLI** - AI-powered terminal assistant (`gh copilot`)
- **Google Gemini CLI** - Google's AI via gcloud CLI (`gcloud ai`)
- **Claude API Client** - Anthropic's Claude API client (`anthropic`)
- **GitHub CLI** - GitHub operations from terminal (`gh`)

### Development Services
- **Mailpit** - Email testing (Web UI at http://localhost:8025)
- **MinIO** - S3-compatible object storage (Console at http://localhost:9001)
- **PostgreSQL** - Database server (port 5432)
- **Redis** - Cache and queue backend (port 6379)

### VS Code Extensions

#### PHP Development
- PHP Intelephense - IntelliSense and code intelligence
- PHP Debug - XDebug integration
- Laravel Extension Pack - Complete Laravel support
- Blade formatter - Blade template formatting
- Better Pest - Pest testing support

#### Frontend Development
- Vue Volar - Official Vue 3 support
- Tailwind CSS IntelliSense - Tailwind autocomplete
- ESLint - JavaScript linting
- Prettier - Code formatting

#### Database
- PostgreSQL support
- SQL Tools with PostgreSQL driver

#### DevOps & Infrastructure
- Docker extension
- Terraform support
- Remote Containers

#### AI Assistants
- GitHub Copilot
- GitHub Copilot Chat

#### Productivity
- GitLens - Enhanced git capabilities
- Git Graph - Visualize git history
- TODO Tree - TODO/FIXME highlighting
- Path Intellisense - Path autocomplete
- REST Client - API testing

## 🚀 Getting Started

### Prerequisites
- **Visual Studio Code** with Remote-Containers extension
- **Docker Desktop** (or Docker Engine + Docker Compose)
- At least **8GB RAM** available for Docker
- At least **20GB disk space**

### Option 1: Open in VS Code (Recommended)

1. Install [VS Code](https://code.visualstudio.com/)
2. Install the [Remote - Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension
3. Clone this repository
4. Open the folder in VS Code
5. When prompted, click "Reopen in Container" (or press `F1` → "Remote-Containers: Reopen in Container")
6. Wait for the container to build (first time takes 10-15 minutes)
7. The setup script will run automatically

### Option 2: Using Dev Container CLI

```bash
# Install dev container CLI
npm install -g @devcontainers/cli

# Build and open the container
devcontainer open .
```

### Option 3: Using GitHub Codespaces

1. Go to the repository on GitHub
2. Click "Code" → "Codespaces" → "Create codespace on main"
3. Wait for the environment to set up
4. Start developing!

## 📦 What Happens During Setup

The setup process automatically:

1. ✅ Builds the development container with all dependencies
2. ✅ Starts PostgreSQL, Redis, Mailpit, and MinIO services
3. ✅ Creates `.env` file from example (if not exists)
4. ✅ Generates Laravel application key
5. ✅ Installs Composer dependencies
6. ✅ Installs npm dependencies
7. ✅ Runs database migrations
8. ✅ Links storage directories
9. ✅ Sets up git hooks
10. ✅ Configures development environment

## 🎮 Using the Dev Container

### Starting the Application

```bash
# Option 1: Manual startup (separate terminals)
php artisan serve              # Terminal 1: Laravel app (http://localhost:8000)
npm run dev                    # Terminal 2: Vite dev server (http://localhost:5173)
php artisan queue:work         # Terminal 3: Queue worker
php artisan reverb:start       # Terminal 4: WebSocket server

# Option 2: Using dev.sh helper script
./dev.sh start                 # Starts everything with Docker Compose
./dev.sh watch                 # Enables backend hot-reloading
./dev.sh help                  # See all available commands
```

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suite
./vendor/bin/pest tests/Enterprise/Feature/

# Run with coverage
./vendor/bin/pest --coverage

# Run browser tests
php artisan dusk
```

### Code Quality

```bash
# Format code (PSR-12)
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Code modernization suggestions
./vendor/bin/rector process --dry-run
```

### Database Operations

```bash
# Run migrations
php artisan migrate

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Seed enterprise data
php artisan db:seed --class=EnterpriseSeeder

# Access database CLI
psql -h postgres -U coolify -d coolify
```

## 🤖 Using AI Assistant CLIs

### GitHub Copilot CLI

```bash
# First time setup
gh auth login
gh extension install github/gh-copilot

# Usage
gh copilot suggest "create a new Laravel migration"
gh copilot explain "php artisan migrate"

# Or use aliases (if configured)
?? "how to create a Laravel controller"
git? "undo last commit"
gh? "create a pull request"
```

### Google Gemini CLI

```bash
# First time setup
gcloud auth login
gcloud config set project YOUR_PROJECT_ID

# Usage (via gcloud AI platform)
gcloud ai models list
gcloud ai predict --model=gemini-pro --text="Explain Laravel Livewire"
```

### Claude API Client

```bash
# Set API key in environment
export ANTHROPIC_API_KEY="your-api-key-here"

# Usage via Python client
python3 -c "import anthropic; client = anthropic.Anthropic(); ..."

# Or use the installed claude-cli package
claude "Explain this code: $(cat app/Models/Organization.php)"
```

## 🔧 Customization

### Adding VS Code Extensions

Edit `.devcontainer/devcontainer.json`:

```json
{
  "customizations": {
    "vscode": {
      "extensions": [
        "your-extension-id"
      ]
    }
  }
}
```

### Modifying Container Configuration

Edit `.devcontainer/Dockerfile` to add system packages:

```dockerfile
RUN apt-get update && apt-get install -y \
    your-package \
    && apt-get clean
```

### Adding Environment Variables

Edit `.devcontainer/docker-compose.yml`:

```yaml
services:
  workspace:
    environment:
      YOUR_VAR: "your_value"
```

### Custom Setup Commands

Edit `.devcontainer/setup.sh` to add custom initialization:

```bash
# Your custom setup commands
echo "Running custom setup..."
```

## 📊 Service Access

| Service | URL | Credentials |
|---------|-----|-------------|
| Laravel App | http://localhost:8000 | test@example.com / password |
| Vite Dev Server | http://localhost:5173 | - |
| Mailpit Web UI | http://localhost:8025 | - |
| MinIO Console | http://localhost:9001 | minioadmin / minioadmin |
| PostgreSQL | localhost:5432 | coolify / password |
| Redis | localhost:6379 | - |
| WebSocket | localhost:6001 | - |

## 🐛 Troubleshooting

### Container Build Fails

```bash
# Clear Docker cache and rebuild
docker system prune -a
# Then rebuild container in VS Code
```

### Services Not Starting

```bash
# Check service status
docker-compose -f .devcontainer/docker-compose.yml ps

# View service logs
docker-compose -f .devcontainer/docker-compose.yml logs postgres
docker-compose -f .devcontainer/docker-compose.yml logs redis
```

### Database Connection Issues

```bash
# Verify PostgreSQL is running
pg_isready -h postgres -U coolify

# Test connection
psql -h postgres -U coolify -d coolify -c "SELECT version();"

# Check environment variables
cat .env | grep DB_
```

### Permission Issues

```bash
# Fix storage permissions
sudo chown -R vscode:vscode storage bootstrap/cache

# Fix git hooks permissions
sudo chmod +x .git/hooks/*
```

### Node Modules Issues

```bash
# Clear and reinstall
rm -rf node_modules package-lock.json
npm install
```

### Composer Issues

```bash
# Clear Composer cache
composer clear-cache

# Remove vendor and reinstall
rm -rf vendor
composer install
```

## 🔄 Updating the Dev Container

When configuration changes are pulled:

1. **Rebuild Container**: Press `F1` → "Remote-Containers: Rebuild Container"
2. **Or**: Stop container and delete, then reopen

## 📚 Additional Resources

- [VS Code Dev Containers Documentation](https://code.visualstudio.com/docs/devcontainers/containers)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Project Development Guidelines](../CLAUDE.md)
- [Project README](../README.md)
- [Setup Steps Reference](../.github/copilot-setup-steps.yml)

## 💡 Tips & Best Practices

### Performance Optimization

1. **Use volume mounts for caching**: Already configured for composer, npm, and VS Code extensions
2. **Keep node_modules in container**: Don't sync with host (performance boost)
3. **Use `:cached` flag**: Applied to workspace mount for better performance

### Development Workflow

1. **Use integrated terminal**: Opens inside container with all tools available
2. **Run tests frequently**: Fast feedback loop with `./vendor/bin/pest --filter=MyTest`
3. **Hot reload**: Keep `npm run dev` and `./dev.sh watch` running
4. **Use AI CLIs**: Speed up development with GitHub Copilot and other AI tools

### Security

1. **Don't commit secrets**: Use `.env` file (already in `.gitignore`)
2. **Rotate credentials**: Change default passwords in production
3. **API keys**: Store in environment variables, never in code

## 🤝 Contributing

When contributing dev container improvements:

1. Test thoroughly with fresh container build
2. Document any new dependencies or tools
3. Update this README with changes
4. Ensure scripts remain idempotent (can run multiple times safely)

## 📝 Files Overview

```
.devcontainer/
├── devcontainer.json       # Main configuration
├── docker-compose.yml      # Services definition
├── Dockerfile              # Container image
├── setup.sh                # Post-create setup script
├── start.sh                # Post-start script
└── README.md              # This file
```

---

**Built for Coolify Enterprise Transformation** - A comprehensive enterprise-grade cloud deployment platform.

For development guidelines, see [CLAUDE.md](../CLAUDE.md)
