<x-layout>
    <x-slot:title>
        License Management
    </x-slot:title>
    <x-slot:head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <style>
            .box {
                @apply bg-white dark:bg-coolgray-100 border border-gray-200 dark:border-coolgray-200 rounded-lg p-6 shadow-sm;
            }
            
            .button {
                @apply bg-coolgray-200 hover:bg-coolgray-300 text-white font-medium py-2 px-4 rounded transition-colors duration-200;
            }
            
            .button-secondary {
                @apply bg-gray-500 hover:bg-gray-600;
            }
            
            .subtitle {
                @apply text-sm text-gray-600 dark:text-gray-400 mt-1;
            }
        </style>
    </x-slot:head>

    <div class="flex items-center gap-2">
        <h1>License Management</h1>
        <x-helper class="inline-flex" helper="Manage enterprise licenses, monitor usage, and control feature access for your organization." />
    </div>

    <div class="subtitle">
        Comprehensive license administration, usage monitoring, and feature management for enterprise deployments.
    </div>

    <!-- Vue.js License Manager Component -->
    <div id="license-manager-app" class="mt-6">
        <!-- Loading state while Vue.js initializes -->
        <div class="box" id="loading-state">
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-coolgray-400"></div>
                <span class="ml-3 text-gray-600 dark:text-gray-400">Loading license management interface...</span>
            </div>
        </div>
    </div>

    <!-- Fallback content if Vue.js fails to load -->
    <script>
        // Hide loading state after 5 seconds if Vue hasn't mounted
        setTimeout(() => {
            const loadingState = document.getElementById('loading-state');
            const app = document.getElementById('license-manager-app');
            if (loadingState && app && app.children.length === 1) {
                loadingState.innerHTML = `
                    <div class="text-center py-8">
                        <p class="text-red-600 dark:text-red-400 mb-4">Failed to load license management interface.</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Please check the browser console for errors and try refreshing the page.</p>
                        <button onclick="window.location.reload()" class="button">Refresh Page</button>
                    </div>
                `;
            }
        }, 5000);
    </script>

    <x-slot:scripts>
        @vite(['resources/js/app.js'])
    </x-slot:scripts>
</x-layout>