/**
 * WebSocket Fallback Handler for Coolify
 * Handles graceful degradation when Soketi/WebSocket connections fail
 */

class WebSocketFallback {
    constructor() {
        this.connectionAttempts = 0;
        this.maxAttempts = 3;
        this.retryDelay = 5000; // 5 seconds
        this.isConnected = false;
        this.fallbackMode = false;
        this.init();
    }

    init() {
        // Listen for Pusher connection events
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            this.setupPusherListeners();
        } else {
            // If Echo is not available, enable fallback mode immediately
            this.enableFallbackMode();
        }
    }

    setupPusherListeners() {
        const pusher = window.Echo.connector.pusher;
        
        pusher.connection.bind('connected', () => {
            this.isConnected = true;
            this.connectionAttempts = 0;
            this.disableFallbackMode();
            console.log('✅ WebSocket connected successfully');
        });

        pusher.connection.bind('disconnected', () => {
            this.isConnected = false;
            console.log('⚠️ WebSocket disconnected');
            this.handleDisconnection();
        });

        pusher.connection.bind('failed', () => {
            this.isConnected = false;
            console.log('❌ WebSocket connection failed');
            this.handleConnectionFailure();
        });

        pusher.connection.bind('error', (error) => {
            console.log('❌ WebSocket error:', error);
            this.handleConnectionFailure();
        });
    }

    handleDisconnection() {
        if (!this.fallbackMode) {
            this.connectionAttempts++;
            if (this.connectionAttempts >= this.maxAttempts) {
                this.enableFallbackMode();
            } else {
                setTimeout(() => {
                    this.attemptReconnection();
                }, this.retryDelay);
            }
        }
    }

    handleConnectionFailure() {
        this.connectionAttempts++;
        if (this.connectionAttempts >= this.maxAttempts) {
            this.enableFallbackMode();
        } else {
            setTimeout(() => {
                this.attemptReconnection();
            }, this.retryDelay);
        }
    }

    attemptReconnection() {
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            console.log(`🔄 Attempting WebSocket reconnection (${this.connectionAttempts}/${this.maxAttempts})`);
            window.Echo.connector.pusher.connect();
        }
    }

    enableFallbackMode() {
        if (this.fallbackMode) return;
        
        this.fallbackMode = true;
        console.log('🔄 Enabling WebSocket fallback mode');
        
        // Hide WebSocket connection error messages
        this.hideConnectionErrors();
        
        // Show fallback notification
        this.showFallbackNotification();
        
        // Enable polling for critical updates
        this.enablePolling();
    }

    disableFallbackMode() {
        if (!this.fallbackMode) return;
        
        this.fallbackMode = false;
        console.log('✅ Disabling WebSocket fallback mode');
        
        // Hide fallback notification
        this.hideFallbackNotification();
        
        // Disable polling
        this.disablePolling();
    }

    hideConnectionErrors() {
        // Suppress console errors about WebSocket connections
        const originalConsoleError = console.error;
        console.error = function(...args) {
            const message = args.join(' ');
            if (message.includes('WebSocket connection') || 
                message.includes('soketi') || 
                message.includes('real-time service')) {
                return; // Suppress these specific errors
            }
            originalConsoleError.apply(console, args);
        };
    }

    showFallbackNotification() {
        // Remove any existing notification
        this.hideFallbackNotification();
        
        const notification = document.createElement('div');
        notification.id = 'websocket-fallback-notification';
        notification.className = 'fixed top-4 right-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded shadow-lg z-50 max-w-sm';
        notification.innerHTML = `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium">Real-time features unavailable</p>
                    <p class="text-xs mt-1">Some features may require page refresh</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-yellow-700 hover:text-yellow-900">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            this.hideFallbackNotification();
        }, 10000);
    }

    hideFallbackNotification() {
        const notification = document.getElementById('websocket-fallback-notification');
        if (notification) {
            notification.remove();
        }
    }

    enablePolling() {
        // Enable periodic polling for critical updates
        this.pollingInterval = setInterval(() => {
            // Trigger Livewire refresh for critical components
            if (window.Livewire) {
                // Refresh organization-related components
                const organizationComponents = document.querySelectorAll('[wire\\:id]');
                organizationComponents.forEach(component => {
                    const componentId = component.getAttribute('wire:id');
                    if (componentId && (componentId.includes('organization') || componentId.includes('hierarchy'))) {
                        try {
                            const livewireComponent = window.Livewire.find(componentId);
                            if (livewireComponent && typeof livewireComponent.call === 'function') {
                                livewireComponent.call('$refresh');
                            }
                        } catch (e) {
                            console.debug('Polling refresh failed for component:', componentId, e);
                        }
                    }
                });
            }
        }, 30000); // Poll every 30 seconds
    }

    disablePolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }
}

// Initialize WebSocket fallback when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.webSocketFallback = new WebSocketFallback();
});

// Also initialize if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.webSocketFallback = new WebSocketFallback();
    });
} else {
    window.webSocketFallback = new WebSocketFallback();
}