<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Connection Test</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        #log { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; height: 300px; overflow-y: auto; font-family: monospace; }
    </style>
</head>
<body>
    <h1>WebSocket Connection Test</h1>
    
    <div id="status" class="status info">Testing connection...</div>
    
    <h2>Connection Log</h2>
    <div id="log"></div>
    
    <button onclick="testConnection()">Test Connection</button>
    <button onclick="clearLog()">Clear Log</button>

    <script>
        let pusher;
        let logElement = document.getElementById('log');
        let statusElement = document.getElementById('status');

        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `[${timestamp}] ${message}\n`;
            logElement.textContent += logEntry;
            logElement.scrollTop = logElement.scrollHeight;
            console.log(message);
        }

        function updateStatus(message, type) {
            statusElement.textContent = message;
            statusElement.className = `status ${type}`;
        }

        function clearLog() {
            logElement.textContent = '';
        }

        function testConnection() {
            log('Starting WebSocket connection test...');
            updateStatus('Testing connection...', 'info');

            try {
                // Test with the same configuration as Coolify
                pusher = new Pusher('coolify', {
                    wsHost: window.location.hostname,
                    wsPort: 6001,
                    wssPort: 6001,
                    forceTLS: false,
                    enabledTransports: ['ws', 'wss'],
                    disableStats: true,
                    cluster: 'mt1'
                });

                pusher.connection.bind('connecting', function() {
                    log('Connecting to WebSocket...');
                    updateStatus('Connecting...', 'warning');
                });

                pusher.connection.bind('connected', function() {
                    log('✅ WebSocket connected successfully!');
                    updateStatus('Connected successfully!', 'success');
                });

                pusher.connection.bind('disconnected', function() {
                    log('⚠️ WebSocket disconnected');
                    updateStatus('Disconnected', 'warning');
                });

                pusher.connection.bind('failed', function() {
                    log('❌ WebSocket connection failed');
                    updateStatus('Connection failed', 'error');
                });

                pusher.connection.bind('error', function(error) {
                    log('❌ WebSocket error: ' + JSON.stringify(error));
                    updateStatus('Connection error', 'error');
                });

                pusher.connection.bind('state_change', function(states) {
                    log(`State changed: ${states.previous} -> ${states.current}`);
                });

                // Try to subscribe to a test channel
                const channel = pusher.subscribe('test-channel');
                
                channel.bind('pusher:subscription_succeeded', function() {
                    log('✅ Successfully subscribed to test channel');
                });

                channel.bind('pusher:subscription_error', function(error) {
                    log('❌ Failed to subscribe to test channel: ' + JSON.stringify(error));
                });

            } catch (error) {
                log('❌ Error initializing Pusher: ' + error.message);
                updateStatus('Initialization error', 'error');
            }
        }

        // Auto-start test when page loads
        window.addEventListener('load', testConnection);
    </script>
</body>
</html>