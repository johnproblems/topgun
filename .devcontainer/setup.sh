#!/bin/bash

# Dev Container Setup Script
# This script runs once after the dev container is created

set -e

echo "🚀 Setting up Coolify Enterprise Development Environment..."

# Navigate to workspace
cd /workspace

# Check if .env exists, if not copy from example
if [ ! -f .env ]; then
    echo "📝 Creating .env file from example..."
    cp .env.example .env
    
    # Generate application key
    echo "🔑 Generating application key..."
    php artisan key:generate
    
    # Set development-specific environment variables
    echo "⚙️  Configuring environment for dev container..."
    sed -i 's/^DB_HOST=.*/DB_HOST=postgres/' .env
    sed -i 's/^DB_DATABASE=.*/DB_DATABASE=coolify/' .env
    sed -i 's/^DB_USERNAME=.*/DB_USERNAME=coolify/' .env
    sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=password/' .env
    sed -i 's/^REDIS_HOST=.*/REDIS_HOST=redis/' .env
    sed -i 's/^MAIL_HOST=.*/MAIL_HOST=mailpit/' .env
    sed -i 's/^MAIL_PORT=.*/MAIL_PORT=1025/' .env
else
    echo "✅ .env file already exists"
fi

# Install PHP dependencies
if [ ! -d "vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "✅ Composer dependencies already installed"
fi

# Install Node dependencies
if [ ! -d "node_modules" ]; then
    echo "📦 Installing npm dependencies..."
    npm install
else
    echo "✅ npm dependencies already installed"
fi

# Wait for PostgreSQL to be ready
echo "⏳ Waiting for PostgreSQL to be ready..."
max_attempts=30
attempt=0
until pg_isready -h postgres -U coolify > /dev/null 2>&1 || [ $attempt -eq $max_attempts ]; do
    attempt=$((attempt + 1))
    echo "   Attempt $attempt of $max_attempts..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "⚠️  Warning: Could not connect to PostgreSQL. You may need to run migrations manually."
else
    echo "✅ PostgreSQL is ready"
    
    # Run migrations
    echo "🗄️  Running database migrations..."
    php artisan migrate --force || echo "⚠️  Migrations failed or already run"
    
    # Seed database (optional)
    if [ "$SEED_DATABASE" = "true" ]; then
        echo "🌱 Seeding database..."
        php artisan db:seed --class=EnterpriseSeeder --force || echo "⚠️  Seeding failed or already done"
    fi
    
    # Set up testing database
    echo "🧪 Setting up testing environment..."
    
    # Create test database if it doesn't exist
    psql -h postgres -U coolify -d postgres -c "CREATE DATABASE coolify_test;" 2>/dev/null || echo "   Test database already exists"
    
    # Update phpunit.xml for dev container environment
    if [ -f phpunit.xml ]; then
        echo "   Updating phpunit.xml for container environment..."
        sed -i 's/<env name="DB_HOST" value=".*"\/>/<env name="DB_HOST" value="postgres"\/>/' phpunit.xml
        sed -i 's/<env name="DB_DATABASE" value=".*"\/>/<env name="DB_DATABASE" value="coolify_test"\/>/' phpunit.xml
        sed -i 's/<env name="REDIS_HOST" value=".*"\/>/<env name="REDIS_HOST" value="redis"\/>/' phpunit.xml
    fi
    
    # Copy .env.testing as reference if needed
    if [ ! -f .env.testing.local ]; then
        cp .env.testing .env.testing.local
        echo "   Created .env.testing.local from .env.testing"
        
        # Update test environment for container
        sed -i 's/^DB_HOST=.*/DB_HOST=postgres/' .env.testing.local
        sed -i 's/^DB_DATABASE=.*/DB_DATABASE=coolify_test/' .env.testing.local
        sed -i 's/^REDIS_HOST=.*/REDIS_HOST=redis/' .env.testing.local
        sed -i 's/^MAIL_HOST=.*/MAIL_HOST=mailpit/' .env.testing.local
    fi
    
    # Run test migrations
    echo "   Running test database migrations..."
    php artisan migrate --env=testing --database=testing --force 2>/dev/null || echo "   Test migrations will run on first test execution"
fi

# Clear and cache config
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Set up git hooks
if [ -d "hooks" ] && [ -d ".git" ]; then
    echo "🔗 Setting up git hooks..."
    cp -r hooks/ .git/hooks/ || true
    chmod +x .git/hooks/* || true
fi

# Verify code quality tools are available
echo "🔍 Verifying code quality tools..."

# Check Pint (Laravel's code formatter)
if [ -f "vendor/bin/pint" ]; then
    echo "   ✅ Pint (code formatter) is ready"
    echo "      Usage: ./vendor/bin/pint"
else
    echo "   ⚠️  Pint not found in vendor/bin"
fi

# Check PHPStan (static analysis)
if [ -f "vendor/bin/phpstan" ]; then
    echo "   ✅ PHPStan (static analysis) is ready"
    echo "      Usage: ./vendor/bin/phpstan analyse"
    
    # Verify phpstan.neon exists
    if [ -f "phpstan.neon" ]; then
        echo "      Configuration: phpstan.neon found"
    fi
    if [ -f "phpstan-baseline.neon" ]; then
        echo "      Baseline: phpstan-baseline.neon found"
    fi
else
    echo "   ⚠️  PHPStan not found in vendor/bin"
fi

# Check Rector (code modernization)
if [ -f "vendor/bin/rector" ]; then
    echo "   ✅ Rector (code modernization) is ready"
    echo "      Usage: ./vendor/bin/rector process --dry-run"
    
    if [ -f "rector.php" ]; then
        echo "      Configuration: rector.php found"
    fi
else
    echo "   ⚠️  Rector not found in vendor/bin"
fi

# Check Pest (testing framework)
if [ -f "vendor/bin/pest" ]; then
    echo "   ✅ Pest (testing framework) is ready"
    echo "      Usage: ./vendor/bin/pest"
    
    if [ -f "phpunit.xml" ]; then
        echo "      Configuration: phpunit.xml found"
    fi
else
    echo "   ⚠️  Pest not found in vendor/bin"
fi

# Create storage directories and set permissions
echo "📁 Setting up storage directories..."
php artisan storage:link || echo "Storage link already exists"

# Install git completion for better terminal experience
if [ ! -f ~/.git-completion.bash ]; then
    echo "📝 Installing git completion..."
    curl -o ~/.git-completion.bash https://raw.githubusercontent.com/git/git/master/contrib/completion/git-completion.bash
    echo "source ~/.git-completion.bash" >> ~/.bashrc
fi

# Configure GitHub Copilot CLI if gh is installed
if command -v gh &> /dev/null; then
    echo "🤖 GitHub CLI is installed. Configure with: gh auth login"
    echo "   Then install Copilot extension: gh extension install github/gh-copilot"
fi

# Configure Gemini CLI
if command -v gcloud &> /dev/null; then
    echo "🔮 Google Cloud CLI is installed. Configure with: gcloud auth login"
    echo "   Then enable Gemini API in your Google Cloud project"
fi

# Display helpful information
echo ""
echo "✨ Dev Container Setup Complete! ✨"
echo ""
echo "📋 Next Steps:"
echo "   1. Start the application:     php artisan serve"
echo "   2. Start queue worker:        php artisan queue:work"
echo "   3. Start WebSocket server:    php artisan reverb:start"
echo "   4. Start frontend dev server: npm run dev"
echo ""
echo "🌐 URLs:"
echo "   • Application:  http://localhost:8000"
echo "   • Vite:         http://localhost:5173"
echo "   • Mailpit:      http://localhost:8025"
echo "   • MinIO:        http://localhost:9001"
echo ""
echo "🔐 Default Login (after seeding):"
echo "   • Email:    test@example.com"
echo "   • Password: password"
echo ""
echo "🧪 Testing:"
echo "   • Run all tests:              ./vendor/bin/pest"
echo "   • Run with coverage:          ./vendor/bin/pest --coverage"
echo "   • Run specific test:          ./vendor/bin/pest --filter=TestName"
echo "   • Enterprise feature tests:   ./vendor/bin/pest tests/Enterprise/Feature/"
echo "   • Enterprise unit tests:      ./vendor/bin/pest tests/Enterprise/Unit/"
echo "   • Browser tests (Dusk):       php artisan dusk"
echo ""
echo "   Testing uses separate database: coolify_test"
echo "   Test configuration: .env.testing.local (created) or phpunit.xml"
echo ""
echo "🔍 Code Quality Tools:"
echo "   • Format code (Pint):         ./vendor/bin/pint"
echo "   • Check formatting:           ./vendor/bin/pint --test"
echo "   • Static analysis:            ./vendor/bin/phpstan analyse"
echo "   • Static analysis (verbose):  ./vendor/bin/phpstan analyse -v"
echo "   • Code modernization:         ./vendor/bin/rector process --dry-run"
echo "   • Apply rector changes:       ./vendor/bin/rector process"
echo ""
echo "   All tools configured with project settings (phpstan.neon, rector.php, pint.json)"
echo ""
echo "🛠️  Useful Commands:"
echo "   • Vite:         http://localhost:5173"
echo "   • Mailpit:      http://localhost:8025"
echo "   • MinIO:        http://localhost:9001"
echo ""
echo "🔐 Default Login (after seeding):"
echo "   • Email:    test@example.com"
echo "   • Password: password"
echo ""
echo "🛠️  Useful Commands:"
echo "   • ./dev.sh help                      - Development helper script"
echo "   • ./vendor/bin/pint                  - Format code"
echo "   • ./vendor/bin/phpstan analyse       - Static analysis"
echo "   • ./vendor/bin/pest                  - Run tests"
echo "   • php artisan                        - Laravel artisan commands"
echo ""
echo "🤖 AI CLI Tools:"
echo "   • gh copilot                         - GitHub Copilot CLI"
echo "   • gcloud ai                          - Google Gemini CLI"
echo "   • anthropic                          - Claude API client"
echo ""
echo "📚 Documentation:"
echo "   • CLAUDE.md                          - Development guidelines"
echo "   • README.md                          - Project overview"
echo "   • .github/copilot-setup-steps.yml    - Setup reference"
echo ""
echo "Happy coding! 🚀"
