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
