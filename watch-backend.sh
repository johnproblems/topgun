#!/bin/bash

# Install inotify-tools if not present
if ! command -v inotifywait &> /dev/null; then
    echo "Installing inotify-tools for file watching..."
    sudo apt-get install -y inotify-tools
fi

echo "🚀 Starting backend file watcher for automatic reloading..."
echo "Watching for changes in PHP files, routes, config, and views..."

# Function to restart the coolify container
restart_coolify() {
    echo "📝 Changes detected! Restarting Coolify container..."
    docker-compose -f docker-compose.dev-full.yml restart coolify
    echo "✅ Coolify restarted!"
}

# Watch for changes in key directories
inotifywait -m -r -e modify,create,delete,move \
    --include='\.php$|\.blade\.php$|\.json$|\.yaml$|\.yml$|\.env$' \
    app/ routes/ config/ resources/views/ database/ composer.json .env bootstrap/ \
    --format '%w%f %e' | while read file event; do
    
    # Debounce - ignore rapid changes
    sleep 1
    
    echo "🔄 File changed: $file ($event)"
    restart_coolify
    
    # Wait before processing next change to avoid rapid restarts
    sleep 3
done
