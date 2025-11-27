<template>
  <div class="license-manager p-4 min-h-screen">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1>License Management</h1>
        <div class="subtitle">Manage enterprise licenses, usage monitoring, and feature access.</div>
      </div>
      
      <div class="flex gap-2">
        <button 
          @click="showIssueModal = true"
          class="button"
          v-if="canIssueLicenses"
        >
          Issue License
        </button>
        <button 
          @click="refreshData"
          class="button button-secondary"
          :disabled="loading"
        >
          {{ loading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="mb-4 p-4 rounded bg-blue-100 border border-blue-400 text-blue-700">
      Loading license data...
    </div>

    <!-- Success/Error Messages -->
    <div v-if="message.text" :class="messageClass" class="mb-4 p-4 rounded">
      {{ message.text }}
    </div>

    <!-- Current License Status -->
    <div v-if="currentLicense" class="box mb-6">
      <div class="flex justify-between items-start mb-4">
        <h2>Current License</h2>
        <div class="flex gap-2">
          <span :class="licenseStatusClass(currentLicense)" class="badge">
            {{ currentLicense.status.toUpperCase() }}
          </span>
          <span :class="licenseTierClass(currentLicense)" class="badge">
            {{ currentLicense.license_tier.toUpperCase() }}
          </span>
        </div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <span class="font-medium">License Key:</span>
          <div class="font-mono text-sm mt-1">{{ formatLicenseKey(currentLicense.license_key) }}</div>
        </div>
        <div>
          <span class="font-medium">Type:</span>
          <div class="capitalize mt-1">{{ currentLicense.license_type }}</div>
        </div>
        <div>
          <span class="font-medium">Issued:</span>
          <div class="mt-1">{{ formatDate(currentLicense.issued_at) }}</div>
        </div>
        <div>
          <span class="font-medium">Expires:</span>
          <div class="mt-1">
            {{ currentLicense.expires_at ? formatDate(currentLicense.expires_at) : 'Never' }}
            <span v-if="currentLicense.expires_at && isExpiringWithin(currentLicense, 30)" class="text-orange-600 ml-2">
              ({{ getDaysUntilExpiration(currentLicense) }} days left)
            </span>
          </div>
        </div>
      </div>

      <!-- License Actions -->
      <div class="flex gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <button 
          v-if="currentLicense.status === 'active'"
          @click="renewLicense(currentLicense)"
          class="btn btn-sm btn-primary"
        >
          Renew License
        </button>
        <button 
          @click="upgradeLicense(currentLicense)"
          class="btn btn-sm btn-secondary"
        >
          Upgrade License
        </button>
        <button 
          @click="viewLicenseDetails(currentLicense)"
          class="btn btn-sm"
        >
          View Details
        </button>
      </div>
    </div>

    <!-- Usage Statistics -->
    <UsageMonitoring 
      v-if="currentLicense && usageStats"
      :license="currentLicense"
      :usage-stats="usageStats"
      class="mb-6"
    />

    <!-- Feature Toggles -->
    <FeatureToggles 
      v-if="currentLicense"
      :license="currentLicense"
      class="mb-6"
    />

    <!-- All Licenses (for admins) -->
    <div v-if="canManageAllLicenses" class="box">
      <div class="flex justify-between items-center mb-4">
        <div>
          <h2>All Licenses</h2>
          <div class="subtitle">Manage all organization licenses.</div>
        </div>
        
        <div class="flex gap-2">
          <select v-model="filterStatus" class="select select-sm">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="suspended">Suspended</option>
            <option value="revoked">Revoked</option>
          </select>
          
          <select v-model="filterTier" class="select select-sm">
            <option value="">All Tiers</option>
            <option value="basic">Basic</option>
            <option value="professional">Professional</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
      </div>
      
      <div class="space-y-4">
        <div 
          v-for="license in filteredLicenses" 
          :key="license.id"
          class="border dark:border-coolgray-200 rounded-lg p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <h4 class="font-medium font-mono text-sm">{{ formatLicenseKey(license.license_key) }}</h4>
                <span :class="licenseStatusClass(license)" class="badge badge-sm">
                  {{ license.status }}
                </span>
                <span :class="licenseTierClass(license)" class="badge badge-sm">
                  {{ license.license_tier }}
                </span>
              </div>
              <div class="mt-1 flex items-center text-sm opacity-75 gap-2">
                <span>{{ license.organization?.name || 'No Organization' }}</span>
                <span>•</span>
                <span class="capitalize">{{ license.license_type }}</span>
                <span>•</span>
                <span>Issued {{ formatDate(license.issued_at) }}</span>
                <span v-if="license.expires_at">•</span>
                <span v-if="license.expires_at">Expires {{ formatDate(license.expires_at) }}</span>
              </div>
            </div>
            
            <div class="flex gap-2">
              <button 
                @click="viewLicenseDetails(license)"
                class="btn btn-sm"
              >
                Details
              </button>
              
              <button 
                v-if="license.status === 'active'"
                @click="suspendLicense(license)"
                class="btn btn-sm btn-warning"
              >
                Suspend
              </button>
              
              <button 
                v-if="license.status === 'suspended'"
                @click="reactivateLicense(license)"
                class="btn btn-sm btn-success"
              >
                Reactivate
              </button>
              
              <button 
                v-if="license.status !== 'revoked'"
                @click="revokeLicense(license)"
                class="btn btn-sm btn-danger"
              >
                Revoke
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="filteredLicenses.length === 0" class="text-center py-8 opacity-75">
          No licenses found matching the current filters.
        </div>
      </div>
    </div>

    <!-- Issue License Modal -->
    <LicenseIssuance 
      v-if="showIssueModal"
      @close="showIssueModal = false"
      @license-issued="onLicenseIssued"
    />

    <!-- License Details Modal -->
    <LicenseDetails 
      v-if="showDetailsModal"
      :license="selectedLicense"
      @close="showDetailsModal = false"
      @license-updated="onLicenseUpdated"
    />

    <!-- License Renewal Modal -->
    <LicenseRenewal 
      v-if="showRenewalModal"
      :license="selectedLicense"
      @close="showRenewalModal = false"
      @license-renewed="onLicenseRenewed"
    />

    <!-- License Upgrade Modal -->
    <LicenseUpgrade 
      v-if="showUpgradeModal"
      :license="selectedLicense"
      @close="showUpgradeModal = false"
      @license-upgraded="onLicenseUpgraded"
    />
  </div>
</template>

<script>
import UsageMonitoring from './UsageMonitoring.vue'
import FeatureToggles from './FeatureToggles.vue'
import LicenseIssuance from './LicenseIssuance.vue'
import LicenseDetails from './LicenseDetails.vue'
import LicenseRenewal from './LicenseRenewal.vue'
import LicenseUpgrade from './LicenseUpgrade.vue'

export default {
  name: 'LicenseManager',
  components: {
    UsageMonitoring,
    FeatureToggles,
    LicenseIssuance,
    LicenseDetails,
    LicenseRenewal,
    LicenseUpgrade
  },
  data() {
    return {
      licenses: [],
      currentLicense: null,
      usageStats: null,
      canIssueLicenses: false,
      canManageAllLicenses: false,
      showIssueModal: false,
      showDetailsModal: false,
      showRenewalModal: false,
      showUpgradeModal: false,
      selectedLicense: null,
      message: { text: '', type: '' },
      loading: true,
      filterStatus: '',
      filterTier: ''
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
    },
    filteredLicenses() {
      return this.licenses.filter(license => {
        if (this.filterStatus && license.status !== this.filterStatus) return false
        if (this.filterTier && license.license_tier !== this.filterTier) return false
        return true
      })
    }
  },
  async mounted() {
    await this.loadData()
  },
  methods: {
    async loadData() {
      try {
        this.loading = true
        const response = await fetch('/internal-api/licenses', {
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
        
        this.licenses = data.licenses || []
        this.currentLicense = data.currentLicense || null
        this.usageStats = data.usageStats || null
        this.canIssueLicenses = data.canIssueLicenses || false
        this.canManageAllLicenses = data.canManageAllLicenses || false
        
      } catch (error) {
        console.error('Error loading license data:', error)
        this.showMessage(`Failed to load license data: ${error.message}`, 'error')
      } finally {
        this.loading = false
      }
    },

    async refreshData() {
      await this.loadData()
      this.showMessage('License data refreshed', 'success')
    },

    async suspendLicense(license) {
      if (!confirm(`Are you sure you want to suspend license ${this.formatLicenseKey(license.license_key)}?`)) {
        return
      }

      try {
        const response = await fetch(`/internal-api/licenses/${license.id}/suspend`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          this.showMessage('License suspended successfully', 'success')
          await this.loadData()
        } else {
          const error = await response.json()
          this.showMessage(error.message || 'Failed to suspend license', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to suspend license', 'error')
      }
    },

    async reactivateLicense(license) {
      try {
        const response = await fetch(`/internal-api/licenses/${license.id}/reactivate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          this.showMessage('License reactivated successfully', 'success')
          await this.loadData()
        } else {
          const error = await response.json()
          this.showMessage(error.message || 'Failed to reactivate license', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to reactivate license', 'error')
      }
    },

    async revokeLicense(license) {
      if (!confirm(`Are you sure you want to REVOKE license ${this.formatLicenseKey(license.license_key)}? This action cannot be undone.`)) {
        return
      }

      try {
        const response = await fetch(`/internal-api/licenses/${license.id}/revoke`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          this.showMessage('License revoked successfully', 'warning')
          await this.loadData()
        } else {
          const error = await response.json()
          this.showMessage(error.message || 'Failed to revoke license', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to revoke license', 'error')
      }
    },

    viewLicenseDetails(license) {
      this.selectedLicense = license
      this.showDetailsModal = true
    },

    renewLicense(license) {
      this.selectedLicense = license
      this.showRenewalModal = true
    },

    upgradeLicense(license) {
      this.selectedLicense = license
      this.showUpgradeModal = true
    },

    onLicenseIssued() {
      this.showIssueModal = false
      this.loadData()
      this.showMessage('License issued successfully', 'success')
    },

    onLicenseUpdated() {
      this.showDetailsModal = false
      this.loadData()
      this.showMessage('License updated successfully', 'success')
    },

    onLicenseRenewed() {
      this.showRenewalModal = false
      this.loadData()
      this.showMessage('License renewed successfully', 'success')
    },

    onLicenseUpgraded() {
      this.showUpgradeModal = false
      this.loadData()
      this.showMessage('License upgraded successfully', 'success')
    },

    formatLicenseKey(key) {
      if (!key) return ''
      // Format as XXXX-XXXX-XXXX-XXXX...
      return key.replace(/(.{4})/g, '$1-').slice(0, -1)
    },

    formatDate(dateString) {
      if (!dateString) return ''
      return new Date(dateString).toLocaleDateString()
    },

    licenseStatusClass(license) {
      const statusClasses = {
        active: 'bg-green-100 text-green-800',
        expired: 'bg-red-100 text-red-800',
        suspended: 'bg-yellow-100 text-yellow-800',
        revoked: 'bg-gray-100 text-gray-800'
      }
      return statusClasses[license.status] || 'bg-gray-100 text-gray-800'
    },

    licenseTierClass(license) {
      const tierClasses = {
        basic: 'bg-blue-100 text-blue-800',
        professional: 'bg-purple-100 text-purple-800',
        enterprise: 'bg-indigo-100 text-indigo-800'
      }
      return tierClasses[license.license_tier] || 'bg-gray-100 text-gray-800'
    },

    isExpiringWithin(license, days) {
      if (!license.expires_at) return false
      const expirationDate = new Date(license.expires_at)
      const warningDate = new Date()
      warningDate.setDate(warningDate.getDate() + days)
      return expirationDate <= warningDate
    },

    getDaysUntilExpiration(license) {
      if (!license.expires_at) return null
      const expirationDate = new Date(license.expires_at)
      const today = new Date()
      const diffTime = expirationDate - today
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      return Math.max(0, diffDays)
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

.badge-sm {
  @apply px-1.5 py-0.5 text-xs;
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

.btn-success {
  @apply bg-green-600 text-white hover:bg-green-700;
}

.btn-warning {
  @apply bg-yellow-600 text-white hover:bg-yellow-700;
}

.btn-danger {
  @apply bg-red-600 text-white hover:bg-red-700;
}

.select {
  @apply border border-gray-300 rounded px-2 py-1;
}

.select-sm {
  @apply text-sm px-2 py-1;
}

.dark .select {
  @apply bg-gray-700 border-gray-600 text-white;
}
</style>