import { createApp } from 'vue'
import { initializeTerminalComponent } from './terminal.js';
import './websocket-fallback.js';
import OrganizationManager from './components/OrganizationManager.vue'

// Initialize Vue apps
document.addEventListener('DOMContentLoaded', () => {
    // Organization Manager
    const orgManagerElement = document.getElementById('organization-manager-app')
    if (orgManagerElement) {
        createApp(OrganizationManager).mount('#organization-manager-app')
    }
});

['livewire:navigated', 'alpine:init'].forEach((event) => {
    document.addEventListener(event, () => {
        // tree-shaking
        if (document.getElementById('terminal-container')) {
            initializeTerminalComponent()
        }
    });
});
