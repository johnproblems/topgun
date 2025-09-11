<template>
  <div class="branding-manager p-4 min-h-screen">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1>White-Label Branding</h1>
        <div class="subtitle">Customize your platform's appearance, logos, and branding settings.</div>
      </div>
      
      <div class="flex gap-2">
        <button 
          @click="previewChanges"
          class="button button-secondary"
          :disabled="!hasChanges"
        >
          Preview Changes
        </button>
        <button 
          @click="saveChanges"
          class="button"
          :disabled="loading || !hasChanges"
        >
          {{ loading ? 'Saving...' : 'Save Changes' }}
        </button>
        <button 
          @click="resetToDefaults"
          class="button button-danger"
          :disabled="loading"
        >
          Reset to Defaults
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="mb-4 p-4 rounded bg-blue-100 border border-blue-400 text-blue-700">
      Loading branding configuration...
    </div>

    <!-- Success/Error Messages -->
    <div v-if="message.text" :class="messageClass" class="mb-4 p-4 rounded">
      {{ message.text }}
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - Configuration -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Basic Branding Settings -->
        <div class="box">
          <h2 class="mb-4">Basic Settings</h2>
          
          <div class="space-y-4">
            <!-- Platform Name -->
            <div>
              <label class="block text-sm font-medium mb-2">Platform Name</label>
              <input 
                v-model="config.platform_name"
                type="text" 
                class="input w-full"
                placeholder="Enter your platform name"
                @input="markAsChanged"
              />
              <p class="text-sm opacity-75 mt-1">This will replace 'Coolify' throughout the interface</p>
            </div>

            <!-- Hide Coolify Branding -->
            <div class="flex items-center gap-3">
              <input 
                v-model="config.hide_coolify_branding"
                type="checkbox" 
                id="hideBranding"
                class="checkbox"
                @change="markAsChanged"
              />
              <label for="hideBranding" class="text-sm font-medium">
                Hide Coolify Branding
              </label>
            </div>
            <p class="text-sm opacity-75 -mt-2 ml-6">Remove Coolify references from footer and about pages</p>
          </div>
        </div>

        <!-- Logo Management -->
        <div class="box">
          <h2 class="mb-4">Logo & Assets</h2>
          <LogoUploader 
            :current-logo="config.logo_url"
            @logo-updated="onLogoUpdated"
            @logo-removed="onLogoRemoved"
          />
        </div>

        <!-- Theme Customization -->
        <div class="box">
          <h2 class="mb-4">Theme Colors</h2>
          <ThemeCustomizer 
            :theme-config="config.theme_config"
            @theme-updated="onThemeUpdated"
          />
        </div>

        <!-- Domain Management -->
        <div class="box">
          <h2 class="mb-4">Custom Domains</h2>
          <DomainManager 
            :custom-domains="config.custom_domains"
            @domains-updated="onDomainsUpdated"
          />
        </div>

        <!-- Email Templates -->
        <div class="box">
          <h2 class="mb-4">Email Templates</h2>
          <EmailTemplateEditor 
            :templates="config.custom_email_templates"
            @templates-updated="onTemplatesUpdated"
          />
        </div>

        <!-- Custom CSS -->
        <div class="box">
          <h2 class="mb-4">Custom CSS</h2>
          
          <div>
            <label class="block text-sm font-medium mb-2">Additional CSS</label>
            <textarea 
              v-model="config.custom_css"
              rows="8"
              class="textarea w-full font-mono text-sm"
              placeholder="/* Add your custom CSS here */&#10;.custom-class {&#10;  /* Your styles */&#10;}"
              @input="markAsChanged"
            ></textarea>
            <p class="text-sm opacity-75 mt-1">
              Custom CSS will be injected after theme variables. Use CSS custom properties for theme consistency.
            </p>
          </div>
        </div>
      </div>

      <!-- Right Column - Live Preview -->
      <div class="lg:col-span-1">
        <div class="sticky top-4">
          <BrandingPreview 
            :config="config"
            :preview-mode="previewMode"
            @preview-closed="previewMode = false"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import LogoUploader from './LogoUploader.vue'
import ThemeCustomizer from './ThemeCustomizer.vue'
import DomainManager from './DomainManager.vue'
import EmailTemplateEditor from './EmailTemplateEditor.vue'
import BrandingPreview from './BrandingPreview.vue'

export default {
  name: 'BrandingManager',
  components: {
    LogoUploader,
    ThemeCustomizer,
    DomainManager,
    EmailTemplateEditor,
    BrandingPreview
  },
  data() {
    return {
      config: {
        platform_name: '',
        logo_url: null,
        theme_config: {},
        custom_domains: [],
        hide_coolify_branding: false,
        custom_email_templates: {},
        custom_css: ''
      },
      originalConfig: {},
      hasChanges: false,
      loading: true,
      message: { text: '', type: '' },
      previewMode: false
    }
  },
  computed: {
    messageClass() {
      return {
        'bg-green-100 border border-green-400 text-green-700': this.message.type === 'success',
        'bg-red-100 border border-red-400 text-red-700': this.message.type === 'error',
        'bg-blue-100 border border-blue-400 text-blue-700': this.message.type === 'info',
        'bg-yellow-100 border border-yellow-400 text-yellow-700': this.message.type === 'warning'
      }
    }
  },
  async mounted() {
    await this.loadBrandingConfig()
  },
  methods: {
    async loadBrandingConfig() {
      try {
        this.loading = true
        const response = await fetch('/internal-api/white-label', {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          }
        })
        
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`)
        }
        
        const data = await response.json()
        this.config = { ...data.config }
        this.originalConfig = { ...data.config }
        this.hasChanges = false
        
      } catch (error) {
        console.error('Error loading branding config:', error)
        this.showMessage(`Failed to load branding configuration: ${error.message}`, 'error')
      } finally {
        this.loading = false
      }
    },

    async saveChanges() {
      try {
        this.loading = true
        const response = await fetch('/internal-api/white-label', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(this.config)
        })

        if (response.ok) {
          const data = await response.json()
          this.originalConfig = { ...this.config }
          this.hasChanges = false
          this.showMessage('Branding configuration saved successfully', 'success')
          
          // Optionally reload the page to apply changes immediately
          if (data.requiresReload) {
            setTimeout(() => {
              window.location.reload()
            }, 2000)
          }
        } else {
          const error = await response.json()
          this.showMessage(error.message || 'Failed to save branding configuration', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to save branding configuration', 'error')
      } finally {
        this.loading = false
      }
    },

    async resetToDefaults() {
      if (!confirm('Are you sure you want to reset all branding settings to defaults? This action cannot be undone.')) {
        return
      }

      try {
        this.loading = true
        const response = await fetch('/internal-api/white-label/reset', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          await this.loadBrandingConfig()
          this.showMessage('Branding configuration reset to defaults', 'success')
        } else {
          const error = await response.json()
          this.showMessage(error.message || 'Failed to reset branding configuration', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to reset branding configuration', 'error')
      } finally {
        this.loading = false
      }
    },

    previewChanges() {
      this.previewMode = true
    },

    markAsChanged() {
      this.hasChanges = JSON.stringify(this.config) !== JSON.stringify(this.originalConfig)
    },

    onLogoUpdated(logoUrl) {
      this.config.logo_url = logoUrl
      this.markAsChanged()
    },

    onLogoRemoved() {
      this.config.logo_url = null
      this.markAsChanged()
    },

    onThemeUpdated(themeConfig) {
      this.config.theme_config = themeConfig
      this.markAsChanged()
    },

    onDomainsUpdated(domains) {
      this.config.custom_domains = domains
      this.markAsChanged()
    },

    onTemplatesUpdated(templates) {
      this.config.custom_email_templates = templates
      this.markAsChanged()
    },

    showMessage(text, type) {
      this.message = { text, type }
      setTimeout(() => {
        this.message = { text: '', type: '' }
      }, 5000)
    }
  }
}
</script>

<style scoped>
.badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.btn {
  @apply px-3 py-2 rounded font-medium cursor-pointer border-none;
}

.btn-sm {
  @apply px-2 py-1 text-sm;
}

.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700;
}

.btn-secondary {
  @apply bg-gray-600 text-white hover:bg-gray-700;
}

.btn-danger {
  @apply bg-red-600 text-white hover:bg-red-700;
}

.input {
  @apply border border-gray-300 rounded px-3 py-2;
}

.textarea {
  @apply border border-gray-300 rounded px-3 py-2 resize-y;
}

.checkbox {
  @apply rounded;
}

.select {
  @apply border border-gray-300 rounded px-2 py-1;
}

.dark .input,
.dark .textarea,
.dark .select {
  @apply bg-gray-700 border-gray-600 text-white;
}
</style>