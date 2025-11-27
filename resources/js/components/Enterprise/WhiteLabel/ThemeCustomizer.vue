<template>
  <div class="theme-customizer">
    <!-- Color Picker Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <div 
        v-for="(colorInfo, colorKey) in colorVariables" 
        :key="colorKey"
        class="color-picker-group"
      >
        <label class="block text-sm font-medium mb-2">{{ colorInfo.label }}</label>
        <div class="flex items-center gap-3">
          <!-- Color Input -->
          <div class="relative">
            <input 
              :value="getCurrentColor(colorKey)"
              @input="updateColor(colorKey, $event.target.value)"
              type="color" 
              class="color-input"
              :title="colorInfo.label"
            />
            <div class="color-preview" :style="{ backgroundColor: getCurrentColor(colorKey) }"></div>
          </div>
          
          <!-- Hex Input -->
          <input 
            :value="getCurrentColor(colorKey)"
            @input="updateColor(colorKey, $event.target.value)"
            type="text" 
            class="hex-input"
            :placeholder="colorInfo.default"
            maxlength="7"
            pattern="^#[0-9a-fA-F]{6}$"
          />
          
          <!-- Reset Button -->
          <button 
            @click="resetColor(colorKey)"
            class="reset-btn"
            :title="`Reset ${colorInfo.label} to default`"
          >
            ↻
          </button>
        </div>
        <p class="text-xs opacity-75 mt-1">{{ colorInfo.description }}</p>
      </div>
    </div>

    <!-- Live Preview Section -->
    <div class="border-t pt-6">
      <h3 class="text-lg font-medium mb-4">Live Preview</h3>
      
      <!-- Preview Container with Applied Theme -->
      <div class="preview-container" :style="previewStyles">
        <div class="preview-content">
          <!-- Header Preview -->
          <div class="preview-header">
            <div class="preview-logo">Logo</div>
            <div class="preview-nav">
              <span class="preview-nav-item">Dashboard</span>
              <span class="preview-nav-item active">Applications</span>
              <span class="preview-nav-item">Servers</span>
            </div>
          </div>
          
          <!-- Main Content Preview -->
          <div class="preview-main">
            <div class="preview-sidebar">
              <div class="preview-sidebar-item">Projects</div>
              <div class="preview-sidebar-item active">Current Project</div>
              <div class="preview-sidebar-item">Settings</div>
            </div>
            
            <div class="preview-content-area">
              <h4 class="preview-title">Sample Application</h4>
              <p class="preview-text">This is how your content will look with the selected theme colors.</p>
              
              <div class="preview-buttons">
                <button class="preview-btn preview-btn-primary">Primary Button</button>
                <button class="preview-btn preview-btn-secondary">Secondary</button>
              </div>
              
              <div class="preview-alerts">
                <div class="preview-alert preview-alert-success">Success message</div>
                <div class="preview-alert preview-alert-warning">Warning message</div>
                <div class="preview-alert preview-alert-error">Error message</div>
                <div class="preview-alert preview-alert-info">Info message</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Advanced Color Tools -->
    <div class="border-t pt-6">
      <h3 class="text-lg font-medium mb-4">Advanced Tools</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Color Palette Generator -->
        <div class="tool-section">
          <h4 class="font-medium mb-2">Generate Palette</h4>
          <div class="flex gap-2">
            <input 
              v-model="paletteBaseColor"
              type="color" 
              class="color-input-sm"
              title="Base color for palette generation"
            />
            <button 
              @click="generatePalette"
              class="btn btn-sm"
            >
              Generate
            </button>
          </div>
          <p class="text-xs opacity-75 mt-1">Generate harmonious colors from a base color</p>
        </div>
        
        <!-- Preset Themes -->
        <div class="tool-section">
          <h4 class="font-medium mb-2">Quick Presets</h4>
          <div class="flex gap-1 flex-wrap">
            <button 
              v-for="preset in themePresets"
              :key="preset.name"
              @click="applyPreset(preset)"
              class="preset-btn"
              :style="{ backgroundColor: preset.primary_color }"
              :title="preset.name"
            >
              {{ preset.name.slice(0, 2) }}
            </button>
          </div>
          <p class="text-xs opacity-75 mt-1">Apply predefined color schemes</p>
        </div>
        
        <!-- Export/Import -->
        <div class="tool-section">
          <h4 class="font-medium mb-2">Export/Import</h4>
          <div class="flex gap-2">
            <button 
              @click="exportTheme"
              class="btn btn-sm"
            >
              Export
            </button>
            <button 
              @click="$refs.importFile.click()"
              class="btn btn-sm btn-secondary"
            >
              Import
            </button>
            <input 
              ref="importFile"
              type="file" 
              accept=".json"
              @change="importTheme"
              class="hidden"
            />
          </div>
          <p class="text-xs opacity-75 mt-1">Save or load theme configurations</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ThemeCustomizer',
  props: {
    themeConfig: {
      type: Object,
      default: () => ({})
    }
  },
  emits: ['theme-updated'],
  data() {
    return {
      localThemeConfig: { ...this.themeConfig },
      paletteBaseColor: '#3b82f6',
      colorVariables: {
        primary_color: {
          label: 'Primary Color',
          description: 'Main brand color for buttons and links',
          default: '#3b82f6'
        },
        secondary_color: {
          label: 'Secondary Color',
          description: 'Secondary interface elements',
          default: '#1f2937'
        },
        accent_color: {
          label: 'Accent Color',
          description: 'Highlighting and emphasis',
          default: '#10b981'
        },
        background_color: {
          label: 'Background Color',
          description: 'Main page background',
          default: '#ffffff'
        },
        text_color: {
          label: 'Text Color',
          description: 'Primary text color',
          default: '#1f2937'
        },
        sidebar_color: {
          label: 'Sidebar Color',
          description: 'Navigation sidebar background',
          default: '#f9fafb'
        },
        border_color: {
          label: 'Border Color',
          description: 'Borders and dividers',
          default: '#e5e7eb'
        },
        success_color: {
          label: 'Success Color',
          description: 'Success messages and indicators',
          default: '#10b981'
        },
        warning_color: {
          label: 'Warning Color',
          description: 'Warning messages and indicators',
          default: '#f59e0b'
        },
        error_color: {
          label: 'Error Color',
          description: 'Error messages and indicators',
          default: '#ef4444'
        },
        info_color: {
          label: 'Info Color',
          description: 'Informational messages',
          default: '#3b82f6'
        }
      },
      themePresets: [
        {
          name: 'Default',
          primary_color: '#3b82f6',
          secondary_color: '#1f2937',
          accent_color: '#10b981',
          background_color: '#ffffff',
          text_color: '#1f2937',
          sidebar_color: '#f9fafb'
        },
        {
          name: 'Dark',
          primary_color: '#60a5fa',
          secondary_color: '#1f2937',
          accent_color: '#34d399',
          background_color: '#111827',
          text_color: '#f9fafb',
          sidebar_color: '#1f2937'
        },
        {
          name: 'Purple',
          primary_color: '#8b5cf6',
          secondary_color: '#581c87',
          accent_color: '#a78bfa',
          background_color: '#ffffff',
          text_color: '#1f2937',
          sidebar_color: '#faf5ff'
        },
        {
          name: 'Green',
          primary_color: '#10b981',
          secondary_color: '#064e3b',
          accent_color: '#34d399',
          background_color: '#ffffff',
          text_color: '#1f2937',
          sidebar_color: '#ecfdf5'
        },
        {
          name: 'Orange',
          primary_color: '#f97316',
          secondary_color: '#9a3412',
          accent_color: '#fb923c',
          background_color: '#ffffff',
          text_color: '#1f2937',
          sidebar_color: '#fff7ed'
        }
      ]
    }
  },
  computed: {
    previewStyles() {
      const colors = this.localThemeConfig
      return {
        '--preview-primary': colors.primary_color || '#3b82f6',
        '--preview-secondary': colors.secondary_color || '#1f2937',
        '--preview-accent': colors.accent_color || '#10b981',
        '--preview-background': colors.background_color || '#ffffff',
        '--preview-text': colors.text_color || '#1f2937',
        '--preview-sidebar': colors.sidebar_color || '#f9fafb',
        '--preview-border': colors.border_color || '#e5e7eb',
        '--preview-success': colors.success_color || '#10b981',
        '--preview-warning': colors.warning_color || '#f59e0b',
        '--preview-error': colors.error_color || '#ef4444',
        '--preview-info': colors.info_color || '#3b82f6'
      }
    }
  },
  watch: {
    themeConfig: {
      handler(newVal) {
        this.localThemeConfig = { ...newVal }
      },
      deep: true
    },
    localThemeConfig: {
      handler() {
        this.$emit('theme-updated', this.localThemeConfig)
      },
      deep: true
    }
  },
  methods: {
    getCurrentColor(colorKey) {
      return this.localThemeConfig[colorKey] || this.colorVariables[colorKey].default
    },

    updateColor(colorKey, value) {
      if (this.isValidColor(value)) {
        this.localThemeConfig[colorKey] = value
      }
    },

    resetColor(colorKey) {
      this.localThemeConfig[colorKey] = this.colorVariables[colorKey].default
    },

    generatePalette() {
      const baseColor = this.paletteBaseColor
      const hsl = this.hexToHsl(baseColor)
      
      // Generate complementary and analogous colors
      this.localThemeConfig.primary_color = baseColor
      this.localThemeConfig.secondary_color = this.hslToHex(hsl.h, hsl.s, Math.max(hsl.l - 0.3, 0.1))
      this.localThemeConfig.accent_color = this.hslToHex((hsl.h + 30) % 360, hsl.s, hsl.l)
      this.localThemeConfig.success_color = this.hslToHex(120, 0.7, 0.4)
      this.localThemeConfig.warning_color = this.hslToHex(45, 0.9, 0.5)
      this.localThemeConfig.error_color = this.hslToHex(0, 0.8, 0.5)
    },

    applyPreset(preset) {
      Object.keys(preset).forEach(key => {
        if (key !== 'name' && this.colorVariables[key]) {
          this.localThemeConfig[key] = preset[key]
        }
      })
    },

    exportTheme() {
      const themeData = {
        name: 'Custom Theme',
        timestamp: new Date().toISOString(),
        colors: { ...this.localThemeConfig }
      }
      
      const blob = new Blob([JSON.stringify(themeData, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `theme-${Date.now()}.json`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    },

    async importTheme(event) {
      const file = event.target.files[0]
      if (!file) return
      
      try {
        const text = await file.text()
        const themeData = JSON.parse(text)
        
        if (themeData.colors) {
          Object.keys(themeData.colors).forEach(key => {
            if (this.colorVariables[key] && this.isValidColor(themeData.colors[key])) {
              this.localThemeConfig[key] = themeData.colors[key]
            }
          })
        }
      } catch (error) {
        alert('Invalid theme file format')
      }
      
      // Reset file input
      event.target.value = ''
    },

    isValidColor(color) {
      return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(color)
    },

    hexToHsl(hex) {
      const r = parseInt(hex.slice(1, 3), 16) / 255
      const g = parseInt(hex.slice(3, 5), 16) / 255
      const b = parseInt(hex.slice(5, 7), 16) / 255

      const max = Math.max(r, g, b)
      const min = Math.min(r, g, b)
      let h, s, l = (max + min) / 2

      if (max === min) {
        h = s = 0
      } else {
        const d = max - min
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
        switch (max) {
          case r: h = (g - b) / d + (g < b ? 6 : 0); break
          case g: h = (b - r) / d + 2; break
          case b: h = (r - g) / d + 4; break
        }
        h /= 6
      }

      return { h: h * 360, s: s * 100, l: l * 100 }
    },

    hslToHex(h, s, l) {
      h = h / 360
      s = s / 100
      l = l / 100

      const hue2rgb = (p, q, t) => {
        if (t < 0) t += 1
        if (t > 1) t -= 1
        if (t < 1/6) return p + (q - p) * 6 * t
        if (t < 1/2) return q
        if (t < 2/3) return p + (q - p) * (2/3 - t) * 6
        return p
      }

      let r, g, b
      if (s === 0) {
        r = g = b = l
      } else {
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s
        const p = 2 * l - q
        r = hue2rgb(p, q, h + 1/3)
        g = hue2rgb(p, q, h)
        b = hue2rgb(p, q, h - 1/3)
      }

      const toHex = (c) => {
        const hex = Math.round(c * 255).toString(16)
        return hex.length === 1 ? '0' + hex : hex
      }

      return `#${toHex(r)}${toHex(g)}${toHex(b)}`
    }
  }
}
</script>

<style scoped>
.color-input {
  @apply w-10 h-10 rounded border-2 cursor-pointer;
  appearance: none;
  background: none;
}

.color-input::-webkit-color-swatch-wrapper {
  @apply p-0 border-none rounded;
}

.color-input::-webkit-color-swatch {
  @apply border-none rounded;
}

.color-preview {
  @apply absolute inset-0 rounded pointer-events-none;
  z-index: -1;
}

.hex-input {
  @apply border border-gray-300 rounded px-2 py-1 text-sm w-20 font-mono;
}

.color-input-sm {
  @apply w-8 h-8 rounded border cursor-pointer;
  appearance: none;
  background: none;
}

.reset-btn {
  @apply px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100;
}

.tool-section {
  @apply border border-gray-200 rounded p-4;
}

.preset-btn {
  @apply w-8 h-8 rounded text-white text-xs font-medium cursor-pointer border-2 border-white hover:scale-110 transition-transform;
}

.btn {
  @apply px-3 py-1 text-sm border border-gray-300 rounded cursor-pointer hover:bg-gray-100;
}

.btn-sm {
  @apply px-2 py-1 text-xs;
}

.btn-secondary {
  @apply bg-gray-200;
}

/* Preview Styles */
.preview-container {
  @apply border-2 rounded-lg p-4;
  background: var(--preview-background);
  color: var(--preview-text);
  border-color: var(--preview-border);
}

.preview-header {
  @apply flex items-center justify-between mb-4 pb-2;
  border-bottom: 1px solid var(--preview-border);
}

.preview-logo {
  @apply font-bold text-lg;
  color: var(--preview-primary);
}

.preview-nav {
  @apply flex gap-4;
}

.preview-nav-item {
  @apply px-3 py-1 rounded text-sm cursor-pointer;
  color: var(--preview-text);
}

.preview-nav-item.active {
  background: var(--preview-primary);
  @apply text-white;
}

.preview-main {
  @apply flex gap-4;
}

.preview-sidebar {
  @apply w-32 space-y-2;
  background: var(--preview-sidebar);
  @apply p-3 rounded;
}

.preview-sidebar-item {
  @apply px-2 py-1 text-sm rounded cursor-pointer;
}

.preview-sidebar-item.active {
  background: var(--preview-primary);
  @apply text-white;
}

.preview-content-area {
  @apply flex-1 space-y-4;
}

.preview-title {
  @apply text-lg font-medium;
  color: var(--preview-text);
}

.preview-text {
  @apply text-sm;
  color: var(--preview-text);
  opacity: 0.8;
}

.preview-buttons {
  @apply flex gap-2;
}

.preview-btn {
  @apply px-4 py-2 rounded text-sm font-medium;
}

.preview-btn-primary {
  background: var(--preview-primary);
  @apply text-white;
}

.preview-btn-secondary {
  background: var(--preview-secondary);
  @apply text-white;
}

.preview-alerts {
  @apply space-y-2;
}

.preview-alert {
  @apply px-3 py-2 rounded text-sm;
}

.preview-alert-success {
  background: var(--preview-success);
  @apply text-white;
}

.preview-alert-warning {
  background: var(--preview-warning);
  @apply text-white;
}

.preview-alert-error {
  background: var(--preview-error);
  @apply text-white;
}

.preview-alert-info {
  background: var(--preview-info);
  @apply text-white;
}

.dark .hex-input,
.dark .reset-btn,
.dark .btn,
.dark .tool-section {
  @apply bg-gray-700 border-gray-600 text-white;
}
</style>