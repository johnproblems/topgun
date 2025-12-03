#!/bin/bash
# Setup script for Coolify Enterprise development environment
# This script automates the environment setup documented in .github/copilot-setup-steps.yml

set -e  # Exit on error

echo "🚀 Setting up Coolify Enterprise Development Environment"
echo "========================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Check prerequisites
echo ""
echo "📋 Checking prerequisites..."
echo "----------------------------"

# Check PHP version
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    echo "PHP version: $PHP_VERSION"
    if (( $(echo "$PHP_VERSION < 8.3" | bc -l) )); then
        print_error "PHP 8.3+ required, found $PHP_VERSION"
        exit 1
    else
        print_warning "PHP $PHP_VERSION found (8.4+ recommended)"
    fi
else
    print_error "PHP not found. Please install PHP 8.4+"
    exit 1
fi

# Check Composer
if command -v composer &> /dev/null; then
    print_status "Composer found: $(composer --version --no-ansi | head -n 1)"
else
    print_error "Composer not found. Please install Composer"
    exit 1
fi

# Check Node.js
if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v | cut -d "v" -f 2 | cut -d "." -f 1)
    if (( NODE_VERSION < 20 )); then
        print_error "Node.js 20+ required, found $NODE_VERSION"
        exit 1
    else
        print_status "Node.js found: $(node -v)"
    fi
else
    print_error "Node.js not found. Please install Node.js 20+"
    exit 1
fi

# Check npm
if command -v npm &> /dev/null; then
    print_status "npm found: $(npm -v)"
else
    print_error "npm not found. Please install npm"
    exit 1
fi

echo ""
echo "🔧 Setting up environment..."
echo "----------------------------"

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    print_status "Creating .env file from .env.development.example"
    cp .env.development.example .env
    
    # Generate application key
    print_status "Generating application key..."
    php artisan key:generate --ansi
else
    print_warning ".env file already exists, skipping creation"
fi

echo ""
echo "📦 Installing dependencies..."
echo "----------------------------"

# Install PHP dependencies
print_status "Installing PHP dependencies (this may take a few minutes)..."
if composer install --no-interaction --prefer-dist; then
    print_status "PHP dependencies installed successfully"
else
    print_error "Failed to install PHP dependencies"
    print_warning "You may need to run: composer install --ignore-platform-reqs"
    print_warning "Note: This repository requires PHP 8.4+, but you have PHP $PHP_VERSION"
    exit 1
fi

# Install Node.js dependencies
echo ""
print_status "Installing Node.js dependencies..."
if npm install; then
    print_status "Node.js dependencies installed successfully"
else
    print_error "Failed to install Node.js dependencies"
    exit 1
fi

echo ""
echo "✅ Setup Complete!"
echo "=================="
echo ""
echo "📝 Next steps:"
echo ""
echo "1. Configure your database in .env:"
echo "   - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD"
echo ""
echo "2. Run migrations:"
echo "   php artisan migrate"
echo ""
echo "3. (Optional) Seed enterprise data:"
echo "   php artisan db:seed --class=EnterpriseSeeder"
echo ""
echo "4. Start development servers (in separate terminals):"
echo "   Terminal 1: php artisan serve"
echo "   Terminal 2: npm run dev"
echo "   Terminal 3: php artisan queue:work"
echo "   Terminal 4: php artisan reverb:start"
echo ""
echo "5. Access the application:"
echo "   http://localhost:8000"
echo ""
echo "📖 For more information, see:"
echo "   - CLAUDE.md - Development guidelines"
echo "   - .github/copilot-setup-steps.yml - Detailed setup instructions"
echo "   - README.md - Project overview"
echo ""
