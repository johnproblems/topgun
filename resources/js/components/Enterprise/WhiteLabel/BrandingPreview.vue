<template>
  <div class="branding-preview">
    <div class="preview-header mb-4">
      <div class="flex items-center justify-between">
        <h4 class="font-medium">Live Preview</h4>
        <div class="preview-controls flex gap-2">
          <select v-model="previewDevice" class="select text-sm">
            <option value="desktop">Desktop</option>
            <option value="tablet">Tablet</option>
            <option value="mobile">Mobile</option>
          </select>
          
          <button 
            v-if="previewMode"
            @click="$emit('preview-closed')"
            class="btn btn-sm"
          >
            Close Preview
          </button>
        </div>
      </div>
    </div>

    <!-- Device Frame -->
    <div class="preview-frame" :class="`device-${previewDevice}`">
      <div class="preview-screen" :style="previewStyles">
        <!-- Mock Application Interface -->
        <div class="mock-interface">
          <!-- Header/Navigation -->
          <div class="mock-header">
            <div class="mock-nav-brand">
              <img 
                v-if="config.logo_url" 
                :src="config.logo_url" 
                :alt="config.platform_name || 'Platform'"
                class="mock-logo"
              />
              <span v-else class="mock-platform-name">
                {{ config.platform_name || 'Platform' }}
              </span>
            </div>
            
            <div class="mock-nav-items">
              <span class="mock-nav-item">Dashboard</span>
              <span class="mock-nav-item active">Applications</span>
              <span class="mock-nav-item">Servers</span>
              <span class="mock-nav-item">Settings</span>
            </div>
            
            <div class="mock-user-menu">
              <div class="mock-avatar"></div>
            </div>
          </div>

          <!-- Main Content Area -->
          <div class="mock-main">
            <!-- Sidebar -->
            <div class="mock-sidebar">
              <div class="mock-sidebar-section">
                <h5 class="mock-sidebar-title">Projects</h5>
                <div class="mock-sidebar-item">Production App</div>
                <div class="mock-sidebar-item active">Staging App</div>
                <div class="mock-sidebar-item">Development</div>
              </div>
              
              <div class="mock-sidebar-section">
                <h5 class="mock-sidebar-title">Resources</h5>
                <div class="mock-sidebar-item">Servers</div>
                <div class="mock-sidebar-item">Databases</div>
                <div class="mock-sidebar-item">Storage</div>
              </div>
            </div>

            <!-- Content Area -->
            <div class="mock-content">
              <div class="mock-page-header">
                <h1 class="mock-page-title">Application Overview</h1>
                <div class="mock-actions">
                  <button class="mock-btn mock-btn-primary">Deploy</button>
                  <button class="mock-btn">Settings</button>
                </div>
              </div>

              <!-- Cards Grid -->
              <div class="mock-cards-grid">
                <div class="mock-card">
                  <div class="mock-card-header">
                    <h3>Status</h3>
                    <div class="mock-status-indicator success"></div>
                  </div>
                  <div class="mock-card-body">
                    <div class="mock-metric">
                      <span class="mock-metric-value">Running</span>
                      <span class="mock-metric-label">Current Status</span>
                    </div>
                  </div>
                </div>

                <div class="mock-card">
                  <div class="mock-card-header">
                    <h3>Deployment</h3>
                  </div>
                  <div class="mock-card-body">
                    <div class="mock-metric">
                      <span class="mock-metric-value">v1.2.3</span>
                      <span class="mock-metric-label">Current Version</span>
                    </div>
                  </div>
                </div>

                <div class="mock-card">
                  <div class="mock-card-header">
                    <h3>Resources</h3>
                  </div>
                  <div class="mock-card-body">
                    <div class="mock-progress">
                      <div class="mock-progress-bar" style="width: 65%"></div>
                    </div>
                    <span class="mock-metric-label">CPU Usage: 65%</span>
                  </div>
                </div>

                <div class="mock-card">
                  <div class="mock-card-header">
                    <h3>Logs</h3>
                  </div>
                  <div class="mock-card-body">
                    <div class="mock-log-entries">
                      <div class="mock-log-entry">
                        <span class="mock-log-timestamp">14:32</span>
                        <span class="mock-log-message">Application started successfully</span>
                      </div>
                      <div class="mock-log-entry">
                        <span class="mock-log-timestamp">14:31</span>
                        <span class="mock-log-message">Database connection established</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Alerts/Notifications -->
              <div class="mock-alerts">
                <div class="mock-alert success">
                  <span class="mock-alert-icon">✓</span>
                  <span>Deployment completed successfully</span>
                </div>
                <div class="mock-alert warning">
                  <span class="mock-alert-icon">⚠</span>
                  <span>High memory usage detected</span>
                </div>
                <div class="mock-alert info">
                  <span class="mock-alert-icon">ℹ</span>
                  <span>New features available in v1.2.4</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer (if branding not hidden) -->
          <div v-if="!config.hide_coolify_branding" class="mock-footer">
            <span class="mock-footer-text">
              Powered by {{ config.platform_name || 'Platform' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Information -->
    <div class="preview-info mt-4 p-3 bg-gray-50 rounded text-sm">
      <div class="preview-details grid grid-cols-2 gap-4">
        <div>
          <strong>Theme Variables:</strong>
          <ul class="mt-1 text-xs space-y-1">
            <li>Primary: {{ getCurrentThemeValue('primary_color') }}</li>
            <li>Secondary: {{ getCurrentThemeValue('secondary_color') }}</li>
            <li>Background: {{ getCurrentThemeValue('background_color') }}</li>
          </ul>
        </div>
        
        <div>
          <strong>Branding:</strong>
          <ul class="mt-1 text-xs space-y-1">
            <li>Platform: {{ config.platform_name || 'Default' }}</li>
            <li>Logo: {{ config.logo_url ? 'Custom' : 'Default' }}</li>
            <li>Coolify Branding: {{ config.hide_coolify_branding ? 'Hidden' : 'Visible' }}</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Comparison Mode -->
    <div v-if="showComparison" class="comparison-mode mt-6">
      <h5 class="font-medium mb-3">Before vs After</h5>
      <div class="comparison-container grid grid-cols-2 gap-4">
        <div class="comparison-panel">
          <h6 class="text-sm font-medium mb-2">Default Theme</h6>
          <div class="comparison-preview">
            <div class="preview-screen default-theme">
              <!-- Simplified default preview -->
              <div class="mock-header default">
                <span class="mock-platform-name">Coolify</span>
              </div>
              <div class="mock-content-simple default">
                <div class="mock-card default">Default Interface</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="comparison-panel">
          <h6 class="text-sm font-medium mb-2">Custom Theme</h6>
          <div class="comparison-preview">
            <div class="preview-screen" :style="previewStyles">
              <!-- Simplified custom preview -->
              <div class="mock-header">
                <span class="mock-platform-name">{{ config.platform_name || 'Platform' }}</span>
              </div>
              <div class="mock-content-simple">
                <div class="mock-card">Custom Interface</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BrandingPreview',
  props: {
    config: {
      type: Object,
      required: true
    },
    previewMode: {
      type: Boolean,
      default: false
    }
  },
  emits: ['preview-closed'],
  data() {
    return {
      previewDevice: 'desktop',
      showComparison: false
    }
  },
  computed: {
    previewStyles() {
      const theme = this.config.theme_config || {}
      
      return {
        '--primary-color': theme.primary_color || '#3b82f6',
        '--secondary-color': theme.secondary_color || '#1f2937',
        '--accent-color': theme.accent_color || '#10b981',
        '--background-color': theme.background_color || '#ffffff',
        '--text-color': theme.text_color || '#1f2937',
        '--sidebar-color': theme.sidebar_color || '#f9fafb',
        '--border-color': theme.border_color || '#e5e7eb',
        '--success-color': theme.success_color || '#10b981',
        '--warning-color': theme.warning_color || '#f59e0b',
        '--error-color': theme.error_color || '#ef4444',
        '--info-color': theme.info_color || '#3b82f6'
      }
    }
  },
  methods: {
    getCurrentThemeValue(key) {
      return this.config.theme_config?.[key] || this.getDefaultThemeValue(key)
    },
    
    getDefaultThemeValue(key) {
      const defaults = {
        primary_color: '#3b82f6',
        secondary_color: '#1f2937',
        accent_color: '#10b981',
        background_color: '#ffffff',
        text_color: '#1f2937',
        sidebar_color: '#f9fafb',
        border_color: '#e5e7eb',
        success_color: '#10b981',
        warning_color: '#f59e0b',
        error_color: '#ef4444',
        info_color: '#3b82f6'
      }
      return defaults[key] || '#000000'
    }
  }
}
</script>

<style scoped>
.preview-frame {
  @apply border border-gray-300 rounded-lg overflow-hidden bg-gray-100 p-4;
}

.device-desktop .preview-screen {
  @apply w-full min-h-96;
}

.device-tablet .preview-screen {
  @apply w-3/4 mx-auto min-h-80;
}

.device-mobile .preview-screen {
  @apply w-1/2 mx-auto min-h-96;
}

.preview-screen {
  @apply rounded bg-white shadow-lg overflow-hidden;
  background-color: var(--background-color);
  color: var(--text-color);
}

.mock-interface {
  @apply flex flex-col h-full min-h-96;
}

.mock-header {
  @apply flex items-center justify-between px-6 py-3 border-b;
  background-color: var(--sidebar-color);
  border-color: var(--border-color);
}

.mock-nav-brand {
  @apply flex items-center gap-3;
}

.mock-logo {
  @apply h-8 object-contain;
}

.mock-platform-name {
  @apply font-bold text-lg;
  color: var(--primary-color);
}

.mock-nav-items {
  @apply flex gap-6;
}

.mock-nav-item {
  @apply px-3 py-1 text-sm cursor-pointer hover:opacity-80 rounded;
}

.mock-nav-item.active {
  background-color: var(--primary-color);
  @apply text-white;
}

.mock-user-menu {
  @apply flex items-center;
}

.mock-avatar {
  @apply w-8 h-8 rounded-full;
  background-color: var(--accent-color);
}

.mock-main {
  @apply flex flex-1;
}

.mock-sidebar {
  @apply w-48 p-4 border-r space-y-6;
  background-color: var(--sidebar-color);
  border-color: var(--border-color);
}

.mock-sidebar-section {
  @apply space-y-2;
}

.mock-sidebar-title {
  @apply font-medium text-sm opacity-75;
}

.mock-sidebar-item {
  @apply px-2 py-1 text-sm rounded cursor-pointer hover:opacity-80;
}

.mock-sidebar-item.active {
  background-color: var(--primary-color);
  @apply text-white;
}

.mock-content {
  @apply flex-1 p-6 space-y-6;
}

.mock-page-header {
  @apply flex items-center justify-between;
}

.mock-page-title {
  @apply text-xl font-bold;
}

.mock-actions {
  @apply flex gap-2;
}

.mock-btn {
  @apply px-4 py-2 text-sm rounded cursor-pointer border;
  border-color: var(--border-color);
}

.mock-btn-primary {
  background-color: var(--primary-color);
  @apply text-white border-transparent;
}

.mock-cards-grid {
  @apply grid grid-cols-2 gap-4;
}

.mock-card {
  @apply border rounded-lg p-4;
  border-color: var(--border-color);
}

.mock-card-header {
  @apply flex items-center justify-between mb-3;
}

.mock-card h3 {
  @apply font-medium;
}

.mock-status-indicator {
  @apply w-3 h-3 rounded-full;
}

.mock-status-indicator.success {
  background-color: var(--success-color);
}

.mock-metric {
  @apply space-y-1;
}

.mock-metric-value {
  @apply block font-semibold;
}

.mock-metric-label {
  @apply text-sm opacity-75;
}

.mock-progress {
  @apply w-full bg-gray-200 rounded-full h-2 mb-2;
}

.mock-progress-bar {
  @apply h-2 rounded-full;
  background-color: var(--primary-color);
}

.mock-log-entries {
  @apply space-y-2;
}

.mock-log-entry {
  @apply flex gap-2 text-sm;
}

.mock-log-timestamp {
  @apply opacity-75 font-mono;
}

.mock-alerts {
  @apply space-y-2;
}

.mock-alert {
  @apply flex items-center gap-2 px-3 py-2 rounded text-sm;
}

.mock-alert.success {
  background-color: var(--success-color);
  @apply text-white;
}

.mock-alert.warning {
  background-color: var(--warning-color);
  @apply text-white;
}

.mock-alert.info {
  background-color: var(--info-color);
  @apply text-white;
}

.mock-footer {
  @apply px-6 py-3 border-t text-center text-sm opacity-75;
  border-color: var(--border-color);
}

.comparison-preview {
  @apply border rounded overflow-hidden;
}

.comparison-preview .preview-screen {
  @apply min-h-32;
}

.mock-content-simple {
  @apply p-4;
}

.default-theme {
  @apply bg-white text-gray-900;
}

.default-theme .mock-header {
  @apply bg-gray-50 border-gray-200;
}

.default-theme .mock-card {
  @apply border-gray-200;
}

.btn {
  @apply px-3 py-1 text-sm border border-gray-300 rounded cursor-pointer hover:bg-gray-100 transition-colors;
}

.btn-sm {
  @apply px-2 py-1 text-xs;
}

.select {
  @apply border border-gray-300 rounded px-2 py-1 text-sm;
}

.dark .preview-frame {
  @apply border-gray-600 bg-gray-800;
}

.dark .btn {
  @apply border-gray-600 hover:bg-gray-700;
}

.dark .select {
  @apply bg-gray-700 border-gray-600 text-white;
}

.dark .preview-info {
  @apply bg-gray-800;
}
</style>