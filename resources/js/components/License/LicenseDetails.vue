<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>License Details</h3>
        <button @click="$emit('close')" class="modal-close">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- License Overview -->
        <div class="license-overview mb-6">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-medium">License Information</h4>
            <div class="flex gap-2">
              <span :class="licenseStatusClass" class="badge">
                {{ license.status.toUpperCase() }}
              </span>
              <span :class="licenseTierClass" class="badge">
                {{ license.license_tier.toUpperCase() }}
              </span>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="info-item">
              <label>License Key</label>
              <div class="font-mono text-sm bg-gray-100 dark:bg-gray-700 p-2 rounded">
                {{ formatLicenseKey(license.license_key) }}
                <button @click="copyLicenseKey" class="ml-2 text-blue-600 hover:text-blue-800">
                  <i class="fas fa-copy"></i>
                </button>
              </div>
            </div>
            
            <div class="info-item">
              <label>Organization</label>
              <div>{{ license.organization?.name || 'No Organization' }}</div>
            </div>
            
            <div class="info-item">
              <label>License Type</label>
              <div class="capitalize">{{ license.license_type }}</div>
            </div>
            
            <div class="info-item">
              <label>License Tier</label>
              <div class="capitalize">{{ license.license_tier }}</div>
            </div>
            
            <div class="info-item">
              <label>Issued Date</label>
              <div>{{ formatDate(license.issued_at) }}</div>
            </div>
            
            <div class="info-item">
              <label>Expiration Date</label>
              <div>
                {{ license.expires_at ? formatDate(license.expires_at) : 'Never' }}
                <span v-if="license.expires_at && isExpiringWithin(30)" class="text-orange-600 ml-2">
                  ({{ getDaysUntilExpiration() }} days left)
                </span>
              </div>
            </div>
            
            <div class="info-item">
              <label>Last Validated</label>
              <div>{{ license.last_validated_at ? formatDate(license.last_validated_at) : 'Never' }}</div>
            </div>
          </div>
        </div>

        <!-- Features -->
        <div class="license-features mb-6">
          <h4 class="text-lg font-medium mb-4">Enabled Features</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div 
              v-for="feature in license.features" 
              :key="feature"
              class="feature-badge"
            >
              <i :class="getFeatureIcon(feature)" class="mr-2"></i>
              {{ formatFeatureName(feature) }}
            </div>
          </div>
          <div v-if="!license.features || license.features.length === 0" class="text-gray-500 italic">
            No features enabled
          </div>
        </div>

        <!-- Usage Limits -->
        <div class="license-limits mb-6">
          <h4 class="text-lg font-medium mb-4">Usage Limits</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div 
              v-for="(limit, type) in license.limits" 
              :key="type"
              class="limit-item"
            >
              <label>{{ formatLimitType(type) }}</label>
              <div class="text-lg font-medium">
                {{ limit === null ? 'Unlimited' : limit.toLocaleString() }}
              </div>
            </div>
          </div>
          <div v-if="!license.limits || Object.keys(license.limits).length === 0" class="text-gray-500 italic">
            No limits configured (unlimited usage)
          </div>
        </div>

        <!-- Authorized Domains -->
        <div class="authorized-domains mb-6">
          <h4 class="text-lg font-medium mb-4">Authorized Domains</h4>
          <div v-if="license.authorized_domains && license.authorized_domains.length > 0" class="space-y-2">
            <div 
              v-for="domain in license.authorized_domains" 
              :key="domain"
              class="domain-item"
            >
              <i class="fas fa-globe mr-2 text-blue-600"></i>
              {{ domain }}
            </div>
          </div>
          <div v-else class="text-gray-500 italic">
            No domain restrictions (can be used on any domain)
          </div>
        </div>

        <!-- Current Usage (if available) -->
        <div v-if="usageStats" class="current-usage mb-6">
          <h4 class="text-lg font-medium mb-4">Current Usage</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div 
              v-for="(stat, type) in usageStats.statistics" 
              :key="type"
              class="usage-stat"
            >
              <label>{{ formatUsageType(type) }}</label>
              <div class="flex items-center justify-between">
                <span class="text-lg font-medium">{{ stat.current }}</span>
                <span v-if="!stat.unlimited" class="text-sm text-gray-500">
                  / {{ stat.limit }}
                </span>
              </div>
              <div v-if="!stat.unlimited" class="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  class="h-2 rounded-full"
                  :class="getUsageBarClass(stat.percentage)"
                  :style="{ width: Math.min(stat.percentage, 100) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- License Actions -->
        <div class="license-actions">
          <h4 class="text-lg font-medium mb-4">Actions</h4>
          <div class="flex flex-wrap gap-3">
            <button 
              v-if="license.status === 'active'"
              @click="validateLicense"
              class="btn btn-primary"
              :disabled="validating"
            >
              {{ validating ? 'Validating...' : 'Validate License' }}
            </button>
            
            <button 
              v-if="license.status === 'active'"
              @click="$emit('license-updated'); showRenewalModal = true"
              class="btn btn-success"
            >
              Renew License
            </button>
            
            <button 
              @click="$emit('license-updated'); showUpgradeModal = true"
              class="btn btn-secondary"
            >
              Upgrade License
            </button>
            
            <button 
              v-if="license.status === 'active' && canSuspend"
              @click="suspendLicense"
              class="btn btn-warning"
            >
              Suspend License
            </button>
            
            <button 
              v-if="license.status === 'suspended' && canReactivate"
              @click="reactivateLicense"
              class="btn btn-success"
            >
              Reactivate License
            </button>
            
            <button 
              v-if="license.status !== 'revoked' && canRevoke"
              @click="revokeLicense"
              class="btn btn-danger"
            >
              Revoke License
            </button>
            
            <button 
              @click="exportLicenseData"
              class="btn btn-secondary"
            >
              Export Data
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LicenseDetails',
  props: {
    license: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'license-updated'],
  data() {
    return {
      usageStats: null,
      validating: false,
      canSuspend: true,
      canReactivate: true,
      canRevoke: true
    }
  },
  computed: {
    licenseStatusClass() {
      const statusClasses = {
        active: 'bg-green-100 text-green-800',
        expired: 'bg-red-100 text-red-800',
        suspended: 'bg-yellow-100 text-yellow-800',
        revoked: 'bg-gray-100 text-gray-800'
      }
      return statusClasses[this.license.status] || 'bg-gray-100 text-gray-800'
    },
    licenseTierClass() {
      const tierClasses = {
        basic: 'bg-blue-100 text-blue-800',
        professional: 'bg-purple-100 text-purple-800',
        enterprise: 'bg-indigo-100 text-indigo-800'
      }
      return tierClasses[this.license.license_tier] || 'bg-gray-100 text-gray-800'
    }
  },
  async mounted() {
    await this.loadUsageStats()
  },
  methods: {
    async loadUsageStats() {
      try {
        const response = await fetch(`/internal-api/licenses/${this.license.id}/usage-stats`, {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          }
        })
        
        if (response.ok) {
          const data = await response.json()
          this.usageStats = data.usageStats
        }
      } catch (error) {
        console.error('Error loading usage stats:', error)
      }
    },

    async validateLicense() {
      try {
        this.validating = true
        const response = await fetch(`/internal-api/licenses/${this.license.id}/validate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          const result = await response.json()
          alert(`License validation: ${result.message}`)
          this.$emit('license-updated')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to validate license')
        }
      } catch (error) {
        console.error('Error validating license:', error)
        alert('Failed to validate license')
      } finally {
        this.validating = false
      }
    },

    async suspendLicense() {
      if (!confirm('Are you sure you want to suspend this license?')) return

      try {
        const response = await fetch(`/internal-api/licenses/${this.license.id}/suspend`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          alert('License suspended successfully')
          this.$emit('license-updated')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to suspend license')
        }
      } catch (error) {
        console.error('Error suspending license:', error)
        alert('Failed to suspend license')
      }
    },

    async reactivateLicense() {
      try {
        const response = await fetch(`/internal-api/licenses/${this.license.id}/reactivate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          alert('License reactivated successfully')
          this.$emit('license-updated')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to reactivate license')
        }
      } catch (error) {
        console.error('Error reactivating license:', error)
        alert('Failed to reactivate license')
      }
    },

    async revokeLicense() {
      if (!confirm('Are you sure you want to REVOKE this license? This action cannot be undone.')) return

      try {
        const response = await fetch(`/internal-api/licenses/${this.license.id}/revoke`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          alert('License revoked successfully')
          this.$emit('license-updated')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to revoke license')
        }
      } catch (error) {
        console.error('Error revoking license:', error)
        alert('Failed to revoke license')
      }
    },

    async exportLicenseData() {
      try {
        const response = await fetch(`/internal-api/licenses/${this.license.id}/export`, {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          }
        })
        
        if (response.ok) {
          const blob = await response.blob()
          const url = window.URL.createObjectURL(blob)
          const a = document.createElement('a')
          a.href = url
          a.download = `license-${this.license.license_key}-${new Date().toISOString().split('T')[0]}.json`
          document.body.appendChild(a)
          a.click()
          window.URL.revokeObjectURL(url)
          document.body.removeChild(a)
        }
      } catch (error) {
        console.error('Error exporting license data:', error)
      }
    },

    copyLicenseKey() {
      navigator.clipboard.writeText(this.license.license_key).then(() => {
        alert('License key copied to clipboard')
      })
    },

    formatLicenseKey(key) {
      if (!key) return ''
      return key.replace(/(.{4})/g, '$1-').slice(0, -1)
    },

    formatDate(dateString) {
      if (!dateString) return ''
      return new Date(dateString).toLocaleDateString()
    },

    formatFeatureName(feature) {
      return feature.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },

    formatLimitType(type) {
      return type.replace(/max_/, '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },

    formatUsageType(type) {
      return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },

    getFeatureIcon(feature) {
      const icons = {
        application_deployment: 'fas fa-rocket',
        database_management: 'fas fa-database',
        ssl_certificates: 'fas fa-lock',
        server_provisioning: 'fas fa-server',
        terraform_integration: 'fas fa-code-branch',
        white_label_branding: 'fas fa-palette',
        organization_hierarchy: 'fas fa-sitemap',
        mfa_authentication: 'fas fa-mobile-alt',
        audit_logging: 'fas fa-clipboard-list',
        multi_cloud_support: 'fas fa-cloud',
        payment_processing: 'fas fa-credit-card',
        domain_management: 'fas fa-globe',
        advanced_rbac: 'fas fa-users-cog',
        compliance_reporting: 'fas fa-file-contract'
      }
      return icons[feature] || 'fas fa-check'
    },

    getUsageBarClass(percentage) {
      if (percentage >= 90) return 'bg-red-500'
      if (percentage >= 75) return 'bg-yellow-500'
      return 'bg-green-500'
    },

    isExpiringWithin(days) {
      if (!this.license.expires_at) return false
      const expirationDate = new Date(this.license.expires_at)
      const warningDate = new Date()
      warningDate.setDate(warningDate.getDate() + days)
      return expirationDate <= warningDate
    },

    getDaysUntilExpiration() {
      if (!this.license.expires_at) return null
      const expirationDate = new Date(this.license.expires_at)
      const today = new Date()
      const diffTime = expirationDate - today
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      return Math.max(0, diffDays)
    }
  }
}
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-content {
  @apply bg-white dark:bg-gray-800 rounded-lg max-w-6xl w-full max-h-[90vh] overflow-y-auto;
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

.modal-body {
  @apply p-6;
}

.info-item {
  @apply space-y-1;
}

.info-item label {
  @apply text-sm font-medium text-gray-600 dark:text-gray-400;
}

.feature-badge {
  @apply inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200;
}

.limit-item, .usage-stat {
  @apply p-3 border border-gray-200 dark:border-gray-600 rounded-lg;
}

.limit-item label, .usage-stat label {
  @apply text-sm font-medium text-gray-600 dark:text-gray-400;
}

.domain-item {
  @apply flex items-center p-2 bg-gray-50 dark:bg-gray-700 rounded;
}

.badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.btn {
  @apply px-4 py-2 rounded font-medium cursor-pointer border-none transition-colors;
}

.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50;
}

.btn-secondary {
  @apply bg-gray-600 text-white hover:bg-gray-700;
}

.btn-success {
  @apply bg-green-600 text-white hover:bg-green-700;
}

.btn-warning {
  @apply bg-yellow-600 text-white hover:bg-yellow-700;
}

.btn-danger {
  @apply bg-red-600 text-white hover:bg-red-700;
}
</style>