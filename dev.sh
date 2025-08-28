#!/bin/bash

# Coolify Production-like Development Environment Manager
# This script helps you manage your production-like Coolify development setup with hot-reloading

set -e

COMPOSE_FILE="docker-compose.dev-full.yml"

show_help() {
    cat << EOF
🚀 Coolify Development Environment Manager

USAGE:
    ./dev.sh [COMMAND]

COMMANDS:
    start           Start all services (default)
    stop            Stop all services
    restart         Restart all services
    status          Show services status
    logs [service]  Show logs for all services or specific service
    watch           Start backend file watcher for auto-reload
    shell           Open shell in coolify container
    db              Connect to database
    build           Rebuild Docker images
    clean           Stop and clean up everything
    help            Show this help

SERVICES:
    coolify         Main Coolify application (http://localhost:8000)
    vite            Frontend dev server with hot-reload (http://localhost:5173)
    soketi          WebSocket server (http://localhost:6001)
    postgres        PostgreSQL database (localhost:5432)
    redis           Redis cache (localhost:6379)
    mailpit         Email testing (http://localhost:8025)
    minio           S3-compatible storage (http://localhost:9001)
    testing-host    SSH testing environment

HOT-RELOADING:
    - Frontend: Automatic via Vite dev server
    - Backend: Run './dev.sh watch' in another terminal

EXAMPLES:
    ./dev.sh start          # Start all services
    ./dev.sh logs coolify   # Show coolify logs
    ./dev.sh watch          # Start file watcher
    ./dev.sh shell          # Open shell in coolify container

Default credentials: test@example.com / password
EOF
}

start_services() {
    echo "🚀 Starting Coolify production-like development environment..."
    docker-compose -f $COMPOSE_FILE up -d
    
    echo ""
    echo "✅ Services started! Here are your URLs:"
    echo "   🌐 Coolify:        http://localhost:8000"
    echo "   ⚡ Vite (hot):     http://localhost:5173"
    echo "   📡 WebSocket:      http://localhost:6001"
    echo "   📧 Mailpit:        http://localhost:8025"
    echo "   🗂️  MinIO:          http://localhost:9001"
    echo ""
    echo "🔐 Login: test@example.com / password"
    echo ""
    echo "💡 TIP: Run './dev.sh watch' in another terminal for backend hot-reloading!"
}

stop_services() {
    echo "🛑 Stopping all services..."
    docker-compose -f $COMPOSE_FILE down
    echo "✅ All services stopped!"
}

restart_services() {
    echo "🔄 Restarting all services..."
    docker-compose -f $COMPOSE_FILE restart
    echo "✅ All services restarted!"
}

show_status() {
    echo "📊 Services Status:"
    docker-compose -f $COMPOSE_FILE ps
}

show_logs() {
    local service=$1
    if [ -z "$service" ]; then
        echo "📋 Showing logs for all services..."
        docker-compose -f $COMPOSE_FILE logs --tail=50 -f
    else
        echo "📋 Showing logs for $service..."
        docker-compose -f $COMPOSE_FILE logs --tail=50 -f $service
    fi
}

watch_backend() {
    echo "👁️  Starting backend file watcher..."
    echo "   Watching: PHP files, Blade templates, config, routes, .env"
    echo "   Press Ctrl+C to stop"
    echo ""
    
    if ! command -v inotifywait &> /dev/null; then
        echo "Installing inotify-tools..."
        sudo apt-get install -y inotify-tools
    fi
    
    # Function to restart coolify container
    restart_coolify() {
        echo "🔄 Changes detected! Restarting Coolify container..."
        docker-compose -f $COMPOSE_FILE restart coolify
        echo "✅ Coolify restarted!"
    }
    
    # Watch for changes
    inotifywait -m -r -e modify,create,delete,move \
        --include='\.php$|\.blade\.php$|\.json$|\.yaml$|\.yml$|\.env$' \
        app/ routes/ config/ resources/views/ database/ composer.json .env bootstrap/ 2>/dev/null | \
        while read file event; do
            echo "📝 File changed: $file"
            restart_coolify
            sleep 2  # Debounce
        done
}

open_shell() {
    echo "🐚 Opening shell in Coolify container..."
    docker-compose -f $COMPOSE_FILE exec coolify bash
}

connect_db() {
    echo "🗄️  Connecting to PostgreSQL database..."
    docker-compose -f $COMPOSE_FILE exec postgres psql -U coolify -d coolify
}

build_images() {
    echo "🔨 Rebuilding Docker images..."
    docker-compose -f $COMPOSE_FILE build --no-cache
    echo "✅ Images rebuilt!"
}

clean_everything() {
    echo "🧹 Cleaning up everything..."
    docker-compose -f $COMPOSE_FILE down -v --remove-orphans
    docker system prune -f
    echo "✅ Everything cleaned up!"
}

# Main script logic
case ${1:-start} in
    start)
        start_services
        ;;
    stop)
        stop_services
        ;;
    restart)
        restart_services
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs $2
        ;;
    watch)
        watch_backend
        ;;
    shell)
        open_shell
        ;;
    db)
        connect_db
        ;;
    build)
        build_images
        ;;
    clean)
        clean_everything
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo "❌ Unknown command: $1"
        echo "Run './dev.sh help' for available commands"
        exit 1
        ;;
esac
