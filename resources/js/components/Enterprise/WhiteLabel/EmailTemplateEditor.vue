<template>
  <div class="email-template-editor">
    <!-- Template Selection -->
    <div class="template-selector mb-6">
      <div class="flex items-center justify-between mb-3">
        <h4 class="font-medium">Email Templates</h4>
        <div class="flex gap-2">
          <button 
            @click="createNewTemplate"
            class="btn btn-sm btn-primary"
          >
            Create Custom
          </button>
          <button 
            @click="exportTemplates"
            class="btn btn-sm"
            :disabled="Object.keys(templates).length === 0"
          >
            Export All
          </button>
        </div>
      </div>
      
      <div class="template-tabs">
        <button 
          v-for="(label, templateKey) in availableTemplates"
          :key="templateKey"
          @click="selectTemplate(templateKey)"
          :class="{ 'active': selectedTemplate === templateKey }"
          class="template-tab"
        >
          {{ label }}
          <span v-if="hasCustomTemplate(templateKey)" class="custom-indicator"></span>
        </button>
      </div>
    </div>

    <!-- Template Editor -->
    <div v-if="selectedTemplate" class="template-editor">
      <div class="editor-container grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Editor Panel -->
        <div class="editor-panel">
          <div class="editor-header mb-4">
            <div class="flex items-center justify-between">
              <h5 class="font-medium">
                {{ availableTemplates[selectedTemplate] }}
                <span v-if="hasUnsavedChanges" class="text-orange-500 text-sm ml-2">*</span>
              </h5>
              
              <div class="flex gap-2">
                <button 
                  @click="resetTemplate"
                  class="btn btn-sm"
                  :disabled="!hasCustomTemplate(selectedTemplate)"
                >
                  Reset to Default
                </button>
                <button 
                  @click="saveTemplate"
                  class="btn btn-sm btn-primary"
                  :disabled="!hasUnsavedChanges || saving"
                >
                  {{ saving ? 'Saving...' : 'Save' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Subject Line Editor -->
          <div class="subject-editor mb-4">
            <label class="block text-sm font-medium mb-2">Subject Line</label>
            <input 
              v-model="currentTemplate.subject"
              type="text" 
              class="input w-full"
              placeholder="Enter email subject"
              @input="markAsChanged"
            />
          </div>

          <!-- HTML Content Editor -->
          <div class="html-editor">
            <label class="block text-sm font-medium mb-2">HTML Content</label>
            <textarea 
              v-model="currentTemplate.html_content"
              rows="16"
              class="textarea w-full font-mono text-sm"
              placeholder="Enter HTML content..."
              @input="markAsChanged"
            ></textarea>
          </div>
        </div>

        <!-- Preview Panel -->
        <div class="preview-panel">
          <div class="preview-header mb-4">
            <div class="flex items-center justify-between">
              <h5 class="font-medium">Preview</h5>
              <button 
                @click="sendTestEmail"
                class="btn btn-sm"
                :disabled="sendingTest"
              >
                {{ sendingTest ? 'Sending...' : 'Send Test' }}
              </button>
            </div>
          </div>

          <!-- Test Data Input -->
          <div class="test-data mb-4">
            <label class="block text-sm font-medium mb-2">Test Email Address</label>
            <input 
              v-model="testEmail"
              type="email" 
              class="input w-full text-sm"
              placeholder="test@example.com"
            />
          </div>

          <!-- Preview Content -->
          <div class="preview-content">
            <!-- Subject Preview -->
            <div class="preview-subject mb-3 p-2 bg-gray-50 rounded">
              <strong class="text-sm">Subject: </strong>
              <span class="text-sm">{{ processedSubject }}</span>
            </div>

            <!-- Content Preview -->
            <div class="preview-body border rounded p-4 max-h-96 overflow-y-auto">
              <div v-html="processedHtmlContent" class="preview-html"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Template Variables Reference -->
    <div class="variables-reference mt-6 p-4 border border-gray-200 rounded">
      <h4 class="font-medium mb-3">Variable Reference</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
        <div class="variable-group">
          <h5 class="font-medium text-sm mb-2">User Variables</h5>
          <ul class="variable-list">
            <li><code>&#123;&#123; user.name &#125;&#125;</code> - User's full name</li>
            <li><code>&#123;&#123; user.email &#125;&#125;</code> - User's email address</li>
            <li><code>&#123;&#123; user.organization &#125;&#125;</code> - Organization name</li>
          </ul>
        </div>
        
        <div class="variable-group">
          <h5 class="font-medium text-sm mb-2">Platform Variables</h5>
          <ul class="variable-list">
            <li><code>&#123;&#123; platform.name &#125;&#125;</code> - Platform name</li>
            <li><code>&#123;&#123; platform.url &#125;&#125;</code> - Platform URL</li>
            <li><code>&#123;&#123; platform.logo &#125;&#125;</code> - Logo URL</li>
          </ul>
        </div>
        
        <div class="variable-group">
          <h5 class="font-medium text-sm mb-2">Action Variables</h5>
          <ul class="variable-list">
            <li><code>&#123;&#123; action.url &#125;&#125;</code> - Action button URL</li>
            <li><code>&#123;&#123; action.text &#125;&#125;</code> - Action button text</li>
            <li><code>&#123;&#123; timestamp &#125;&#125;</code> - Current timestamp</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'EmailTemplateEditor',
  props: {
    templates: {
      type: Object,
      default: () => ({})
    }
  },
  emits: ['templates-updated'],
  data() {
    return {
      localTemplates: { ...this.templates },
      selectedTemplate: 'welcome',
      hasUnsavedChanges: false,
      saving: false,
      sendingTest: false,
      testEmail: '',
      currentTemplate: {
        subject: '',
        html_content: ''
      },
      availableTemplates: {
        'welcome': 'Welcome Email',
        'password_reset': 'Password Reset',
        'email_verification': 'Email Verification',
        'invitation': 'Team Invitation',
        'deployment_success': 'Deployment Success',
        'deployment_failure': 'Deployment Failure',
        'server_unreachable': 'Server Unreachable',
        'backup_success': 'Backup Success',
        'backup_failure': 'Backup Failure'
      },
      defaultTemplates: {
        welcome: {
          subject: 'Welcome to {{ platform.name }}',
          html_content: `<h1>Welcome to {{ platform.name }}, {{ user.name }}!</h1>
<p>We're excited to have you join our platform. Your account has been successfully created.</p>
<p><a href="{{ action.url }}" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">{{ action.text }}</a></p>
<p>If you have any questions, feel free to reach out to our support team.</p>
<p>Best regards,<br>{{ platform.name }} Team</p>`
        }
      }
    }
  },
  computed: {
    processedSubject() {
      return this.processTemplate(this.currentTemplate.subject)
    },
    
    processedHtmlContent() {
      return this.processTemplate(this.currentTemplate.html_content)
    }
  },
  watch: {
    templates(newVal) {
      this.localTemplates = { ...newVal }
    },
    
    localTemplates: {
      handler() {
        this.$emit('templates-updated', this.localTemplates)
      },
      deep: true
    },
    
    selectedTemplate() {
      this.loadCurrentTemplate()
    }
  },
  mounted() {
    this.loadCurrentTemplate()
  },
  methods: {
    selectTemplate(templateKey) {
      if (this.hasUnsavedChanges) {
        if (!confirm('You have unsaved changes. Continue without saving?')) {
          return
        }
      }
      this.selectedTemplate = templateKey
      this.hasUnsavedChanges = false
    },

    loadCurrentTemplate() {
      const template = this.localTemplates[this.selectedTemplate] || this.getDefaultTemplate(this.selectedTemplate)
      
      this.currentTemplate = {
        subject: template.subject || '',
        html_content: template.html_content || ''
      }
    },

    getDefaultTemplate(templateKey) {
      return this.defaultTemplates[templateKey] || {
        subject: `Default ${this.availableTemplates[templateKey]} Subject`,
        html_content: `<p>Default ${this.availableTemplates[templateKey]} content</p>`
      }
    },

    hasCustomTemplate(templateKey) {
      return !!this.localTemplates[templateKey]
    },

    markAsChanged() {
      this.hasUnsavedChanges = true
    },

    async saveTemplate() {
      this.saving = true
      
      try {
        this.localTemplates[this.selectedTemplate] = { ...this.currentTemplate }
        
        // Save to backend
        const response = await fetch('/internal-api/white-label/email-templates', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({
            template_key: this.selectedTemplate,
            template: this.currentTemplate
          })
        })

        if (response.ok) {
          this.hasUnsavedChanges = false
        } else {
          throw new Error('Failed to save template')
        }
      } catch (error) {
        alert('Failed to save template: ' + error.message)
      } finally {
        this.saving = false
      }
    },

    async resetTemplate() {
      if (!confirm('Reset this template to default? This will lose all custom changes.')) {
        return
      }

      try {
        const response = await fetch(`/internal-api/white-label/email-templates/${this.selectedTemplate}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          delete this.localTemplates[this.selectedTemplate]
          this.loadCurrentTemplate()
          this.hasUnsavedChanges = false
        } else {
          throw new Error('Failed to reset template')
        }
      } catch (error) {
        alert('Failed to reset template: ' + error.message)
      }
    },

    processTemplate(template) {
      if (!template) return ''
      
      // Sample data for preview
      const sampleData = {
        user: {
          name: 'John Doe',
          email: 'john@example.com',
          organization: 'Acme Corp'
        },
        platform: {
          name: 'MyPlatform',
          url: 'https://myplatform.com',
          logo: 'https://myplatform.com/logo.png'
        },
        action: {
          url: 'https://myplatform.com/action',
          text: 'Get Started'
        },
        timestamp: new Date().toLocaleString()
      }
      
      return template.replace(/\{\{\s*([^}]+)\s*\}\}/g, (match, path) => {
        const keys = path.trim().split('.')
        let value = sampleData
        
        for (const key of keys) {
          value = value?.[key]
        }
        
        return value || match
      })
    },

    async sendTestEmail() {
      if (!this.testEmail) {
        alert('Please enter a test email address')
        return
      }

      this.sendingTest = true

      try {
        const response = await fetch('/internal-api/white-label/email-templates/test', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({
            template_key: this.selectedTemplate,
            template: this.currentTemplate,
            test_email: this.testEmail
          })
        })

        if (response.ok) {
          alert('Test email sent successfully!')
        } else {
          throw new Error('Failed to send test email')
        }
      } catch (error) {
        alert('Failed to send test email: ' + error.message)
      } finally {
        this.sendingTest = false
      }
    },

    createNewTemplate() {
      const key = prompt('Enter template key (e.g., "custom_notification"):')
      if (!key || this.availableTemplates[key]) {
        alert('Invalid or duplicate template key')
        return
      }

      const name = prompt('Enter template name:')
      if (!name) return

      this.availableTemplates[key] = name
      this.selectedTemplate = key
      this.currentTemplate = {
        subject: '',
        html_content: ''
      }
      this.hasUnsavedChanges = true
    },

    exportTemplates() {
      const data = {
        version: '1.0',
        exported_at: new Date().toISOString(),
        templates: this.localTemplates
      }
      
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `email-templates-${Date.now()}.json`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    }
  }
}
</script>

<style scoped>
.template-tabs {
  @apply flex flex-wrap gap-2 mb-4;
}

.template-tab {
  @apply px-4 py-2 text-sm border border-gray-300 rounded cursor-pointer hover:bg-gray-100 relative;
}

.template-tab.active {
  @apply bg-blue-600 text-white border-blue-600;
}

.custom-indicator {
  @apply absolute -top-1 -right-1 w-2 h-2 bg-orange-500 rounded-full;
}

.preview-html {
  @apply prose max-w-none;
}

.variable-list {
  @apply space-y-1 text-xs;
}

.variable-list code {
  @apply bg-gray-100 px-1 rounded font-mono;
}

.btn {
  @apply px-3 py-1 text-sm border border-gray-300 rounded cursor-pointer hover:bg-gray-100 transition-colors;
}

.btn-sm {
  @apply px-2 py-1 text-xs;
}

.btn-primary {
  @apply bg-blue-600 text-white border-blue-600 hover:bg-blue-700;
}

.btn:disabled {
  @apply opacity-50 cursor-not-allowed;
}

.input, .textarea {
  @apply border border-gray-300 rounded px-3 py-2;
}

.dark .template-tab,
.dark .btn {
  @apply border-gray-600;
}

.dark .template-tab:hover,
.dark .btn:hover {
  @apply bg-gray-700;
}

.dark .input,
.dark .textarea {
  @apply bg-gray-700 border-gray-600 text-white;
}

.dark .variable-list code {
  @apply bg-gray-700;
}
</style>