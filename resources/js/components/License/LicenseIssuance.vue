<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Issue New License</h3>
        <button @click="$emit('close')" class="modal-close">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <form @submit.prevent="issueLicense" class="space-y-6">
        <!-- Organization Selection -->
        <div>
          <label class="form-label">Organization *</label>
          <select v-model="form.organization_id" required class="form-select">
            <option value="">Select Organization</option>
            <option v-for="org in organizations" :key="org.id" :value="org.id">
              {{ org.name }} ({{ org.hierarchy_type }})
            </option>
          </select>
          <div class="form-help">Select the organization that will own this license.</div>
        </div>

        <!-- License Type -->
        <div>
          <label class="form-label">License Type *</label>
          <div class="radio-group">
            <label class="radio-option">
              <input v-model="form.license_type" type="radio" value="trial" />
              <span class="radio-label">
                <strong>Trial</strong>
                <span class="text-sm text-gray-600">Limited time evaluation license</span>
              </span>
            </label>
            <label class="radio-option">
              <input v-model="form.license_type" type="radio" value="subscription" />
              <span class="radio-label">
                <strong>Subscription</strong>
                <span class="text-sm text-gray-600">Recurring subscription license</span>
              </span>
            </label>
            <label class="radio-option">
              <input v-model="form.license_type" type="radio" value="perpetual" />
              <span class="radio-label">
                <strong>Perpetual</strong>
                <span class="text-sm text-gray-600">One-time purchase, never expires</span>
              </span>
            </label>
          </div>
        </div>

        <!-- License Tier -->
        <div>
          <label class="form-label">License Tier *</label>
          <div class="radio-group">
            <label class="radio-option">
              <input v-model="form.license_tier" type="radio" value="basic" />
              <span class="radio-label">
                <strong>Basic</strong>
                <span class="text-sm text-gray-600">Core features for small teams</span>
              </span>
            </label>
            <label class="radio-option">
              <input v-model="form.license_tier" type="radio" value="professional" />
              <span class="radio-label">
                <strong>Professional</strong>
                <span class="text-sm text-gray-600">Advanced features for growing businesses</span>
              </span>
            </label>
            <label class="radio-option">
              <input v-model="form.license_tier" type="radio" value="enterprise" />
              <span class="radio-label">
                <strong>Enterprise</strong>
                <span class="text-sm text-gray-600">Full feature set for large organizations</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Expiration Date -->
        <div v-if="form.license_type !== 'perpetual'">
          <label class="form-label">Expiration Date</label>
          <input 
            v-model="form.expires_at" 
            type="datetime-local" 
            class="form-input"
            :min="minExpirationDate"
          />
          <div class="form-help">Leave empty for no expiration date.</div>
        </div>

        <!-- Features Selection -->
        <div>
          <label class="form-label">Features</label>
          <div class="feature-selection">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <label 
                v-for="feature in availableFeatures" 
                :key="feature.key"
                class="feature-checkbox"
                :class="{ 'opacity-50': !isFeatureAvailableForTier(feature.key) }"
              >
                <input 
                  v-model="form.features" 
                  type="checkbox" 
                  :value="feature.key"
                  :disabled="!isFeatureAvailableForTier(feature.key)"
                />
                <span class="feature-checkbox-label">
                  <strong>{{ feature.name }}</strong>
                  <span class="text-sm text-gray-600">{{ feature.description }}</span>
                  <span v-if="feature.requiredTier" class="feature-tier-badge">
                    {{ feature.requiredTier }}+
                  </span>
                </span>
              </label>
            </div>
          </div>
        </div>

        <!-- Usage Limits -->
        <div>
          <label class="form-label">Usage Limits</label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="form-sublabel">Max Users</label>
              <input 
                v-model.number="form.limits.max_users" 
                type="number" 
                min="1" 
                class="form-input"
                placeholder="Unlimited"
              />
            </div>
            <div>
              <label class="form-sublabel">Max Servers</label>
              <input 
                v-model.number="form.limits.max_servers" 
                type="number" 
                min="1" 
                class="form-input"
                placeholder="Unlimited"
              />
            </div>
            <div>
              <label class="form-sublabel">Max Applications</label>
              <input 
                v-model.number="form.limits.max_applications" 
                type="number" 
                min="1" 
                class="form-input"
                placeholder="Unlimited"
              />
            </div>
            <div>
              <label class="form-sublabel">Max Domains</label>
              <input 
                v-model.number="form.limits.max_domains" 
                type="number" 
                min="1" 
                class="form-input"
                placeholder="Unlimited"
              />
            </div>
          </div>
          <div class="form-help">Leave empty for unlimited usage.</div>
        </div>

        <!-- Authorized Domains -->
        <div>
          <label class="form-label">Authorized Domains</label>
          <div class="space-y-2">
            <div 
              v-for="(domain, index) in form.authorized_domains" 
              :key="index"
              class="flex gap-2"
            >
              <input 
                v-model="form.authorized_domains[index]" 
                type="text" 
                class="form-input flex-1"
                placeholder="example.com or *.example.com"
              />
              <button 
                type="button" 
                @click="removeDomain(index)"
                class="btn btn-sm btn-danger"
              >
                Remove
              </button>
            </div>
            <button 
              type="button" 
              @click="addDomain"
              class="btn btn-sm btn-secondary"
            >
              Add Domain
            </button>
          </div>
          <div class="form-help">
            Restrict license usage to specific domains. Use *.domain.com for wildcards.
            Leave empty to allow any domain.
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="$emit('close')" class="btn btn-secondary">
            Cancel
          </button>
          <button type="submit" :disabled="submitting" class="btn btn-primary">
            {{ submitting ? 'Issuing License...' : 'Issue License' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LicenseIssuance',
  emits: ['close', 'license-issued'],
  data() {
    return {
      organizations: [],
      availableFeatures: [
        { key: 'application_deployment', name: 'Application Deployment', description: 'Deploy applications from Git', requiredTier: 'basic' },
        { key: 'database_management', name: 'Database Management', description: 'Create and manage databases', requiredTier: 'basic' },
        { key: 'ssl_certificates', name: 'SSL Certificates', description: 'Automatic SSL provisioning', requiredTier: 'basic' },
        { key: 'server_provisioning', name: 'Server Provisioning', description: 'Provision cloud servers', requiredTier: 'professional' },
        { key: 'terraform_integration', name: 'Terraform Integration', description: 'Infrastructure as Code', requiredTier: 'professional' },
        { key: 'white_label_branding', name: 'White-Label Branding', description: 'Custom branding and themes', requiredTier: 'professional' },
        { key: 'organization_hierarchy', name: 'Organization Hierarchy', description: 'Multi-tenant management', requiredTier: 'professional' },
        { key: 'mfa_authentication', name: 'Multi-Factor Auth', description: 'Enhanced security with MFA', requiredTier: 'professional' },
        { key: 'audit_logging', name: 'Audit Logging', description: 'Compliance and audit trails', requiredTier: 'professional' },
        { key: 'multi_cloud_support', name: 'Multi-Cloud Support', description: 'Deploy across multiple clouds', requiredTier: 'enterprise' },
        { key: 'payment_processing', name: 'Payment Processing', description: 'Integrated billing systems', requiredTier: 'enterprise' },
        { key: 'domain_management', name: 'Domain Management', description: 'Purchase and manage domains', requiredTier: 'enterprise' },
        { key: 'advanced_rbac', name: 'Advanced RBAC', description: 'Custom role permissions', requiredTier: 'enterprise' },
        { key: 'compliance_reporting', name: 'Compliance Reporting', description: 'GDPR, PCI-DSS, SOC 2', requiredTier: 'enterprise' }
      ],
      form: {
        organization_id: '',
        license_type: 'subscription',
        license_tier: 'basic',
        expires_at: '',
        features: [],
        limits: {
          max_users: null,
          max_servers: null,
          max_applications: null,
          max_domains: null
        },
        authorized_domains: []
      },
      submitting: false
    }
  },
  computed: {
    minExpirationDate() {
      const now = new Date()
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
      return now.toISOString().slice(0, 16)
    }
  },
  async mounted() {
    await this.loadOrganizations()
    this.setDefaultFeatures()
  },
  watch: {
    'form.license_tier'() {
      this.setDefaultFeatures()
    }
  },
  methods: {
    async loadOrganizations() {
      try {
        const response = await fetch('/internal-api/organizations', {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          }
        })
        
        if (response.ok) {
          const data = await response.json()
          this.organizations = data.organizations || []
        }
      } catch (error) {
        console.error('Error loading organizations:', error)
      }
    },

    setDefaultFeatures() {
      // Set default features based on tier
      const tierFeatures = {
        basic: ['application_deployment', 'database_management', 'ssl_certificates'],
        professional: [
          'application_deployment', 'database_management', 'ssl_certificates',
          'server_provisioning', 'terraform_integration', 'white_label_branding',
          'organization_hierarchy', 'mfa_authentication', 'audit_logging'
        ],
        enterprise: this.availableFeatures.map(f => f.key)
      }
      
      this.form.features = tierFeatures[this.form.license_tier] || []
    },

    isFeatureAvailableForTier(featureKey) {
      const feature = this.availableFeatures.find(f => f.key === featureKey)
      if (!feature) return true
      
      const tierHierarchy = ['basic', 'professional', 'enterprise']
      const currentTierIndex = tierHierarchy.indexOf(this.form.license_tier)
      const requiredTierIndex = tierHierarchy.indexOf(feature.requiredTier)
      
      return requiredTierIndex <= currentTierIndex
    },

    addDomain() {
      this.form.authorized_domains.push('')
    },

    removeDomain(index) {
      this.form.authorized_domains.splice(index, 1)
    },

    async issueLicense() {
      try {
        this.submitting = true
        
        // Clean up form data
        const formData = {
          ...this.form,
          authorized_domains: this.form.authorized_domains.filter(domain => domain.trim()),
          limits: Object.fromEntries(
            Object.entries(this.form.limits).filter(([key, value]) => value !== null && value !== '')
          )
        }
        
        const response = await fetch('/internal-api/licenses', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(formData)
        })

        if (response.ok) {
          this.$emit('license-issued')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to issue license')
        }
      } catch (error) {
        console.error('Error issuing license:', error)
        alert('Failed to issue license')
      } finally {
        this.submitting = false
      }
    }
  }
}
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-content {
  @apply bg-white dark:bg-gray-800 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto;
}

.modal-header {
  @apply flex justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700;
}

.modal-header h3 {
  @apply text-lg font-medium text-gray-900 dark:text-gray-100;
}

.modal-close {
  @apply text-gray-400 hover:text-gray-600 dark:hover:text-gray-300;
}

.form-label {
  @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2;
}

.form-sublabel {
  @apply block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1;
}

.form-input, .form-select {
  @apply w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white;
}

.form-help {
  @apply mt-1 text-sm text-gray-500 dark:text-gray-400;
}

.radio-group {
  @apply space-y-3;
}

.radio-option {
  @apply flex items-start space-x-3 p-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700;
}

.radio-option input[type="radio"] {
  @apply mt-1;
}

.radio-label {
  @apply flex flex-col;
}

.feature-selection {
  @apply border border-gray-200 dark:border-gray-600 rounded-lg p-4;
}

.feature-checkbox {
  @apply flex items-start space-x-3 p-2 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700;
}

.feature-checkbox input[type="checkbox"] {
  @apply mt-1;
}

.feature-checkbox-label {
  @apply flex flex-col;
}

.feature-tier-badge {
  @apply inline-block px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full mt-1;
}

.btn {
  @apply px-4 py-2 rounded font-medium cursor-pointer border-none transition-colors;
}

.btn-sm {
  @apply px-2 py-1 text-sm;
}

.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-secondary {
  @apply bg-gray-600 text-white hover:bg-gray-700;
}

.btn-danger {
  @apply bg-red-600 text-white hover:bg-red-700;
}

form {
  @apply p-6;
}
</style>