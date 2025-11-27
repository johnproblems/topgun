<template>
  <div class="domain-manager">
    <!-- Current Domains List -->
    <div v-if="domains.length > 0" class="current-domains mb-6">
      <h4 class="font-medium mb-3">Custom Domains</h4>
      <div class="domains-list space-y-3">
        <div 
          v-for="(domain, index) in domains" 
          :key="index"
          class="domain-item"
        >
          <div class="domain-info">
            <div class="domain-name">
              {{ domain.domain || domain }}
              <span v-if="typeof domain === 'object'" :class="statusClass(domain.status)" class="status-badge">
                {{ domain.status || 'pending' }}
              </span>
            </div>
            <div v-if="typeof domain === 'object'" class="domain-details">
              <span class="text-xs text-gray-500">
                Added {{ formatDate(domain.added_at) }}
                <span v-if="domain.verified_at">• Verified {{ formatDate(domain.verified_at) }}</span>
              </span>
            </div>
          </div>
          
          <div class="domain-actions">
            <button 
              v-if="typeof domain === 'object' && domain.status === 'pending'"
              @click="verifyDomain(domain.domain || domain, index)"
              class="btn btn-sm btn-primary"
              :disabled="verifying"
            >
              {{ verifying === index ? 'Verifying...' : 'Verify' }}
            </button>
            
            <button 
              v-if="typeof domain === 'object' && domain.status === 'verified'"
              @click="checkDnsStatus(domain.domain || domain, index)"
              class="btn btn-sm"
              :disabled="checking"
            >
              {{ checking === index ? 'Checking...' : 'Check DNS' }}
            </button>
            
            <button 
              @click="removeDomain(index)"
              class="btn btn-sm btn-danger"
              :disabled="removing === index"
            >
              {{ removing === index ? 'Removing...' : 'Remove' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add New Domain -->
    <div class="add-domain">
      <h4 class="font-medium mb-3">Add Custom Domain</h4>
      
      <div class="domain-input-section">
        <div class="flex gap-2 mb-3">
          <input 
            v-model="newDomain"
            type="text" 
            placeholder="example.com"
            class="input flex-1"
            @keyup.enter="addDomain"
            @input="validateDomain"
          />
          <button 
            @click="addDomain"
            class="btn"
            :disabled="!isValidDomain || adding"
          >
            {{ adding ? 'Adding...' : 'Add Domain' }}
          </button>
        </div>
        
        <div v-if="domainError" class="text-sm text-red-600 mb-3">
          {{ domainError }}
        </div>
        
        <div v-if="domainSuccess" class="text-sm text-green-600 mb-3">
          {{ domainSuccess }}
        </div>
      </div>
    </div>

    <!-- DNS Configuration Help -->
    <div v-if="showDnsHelp" class="dns-help mt-6 p-4 border border-blue-200 bg-blue-50 rounded">
      <div class="flex justify-between items-start mb-3">
        <h4 class="font-medium text-blue-800">DNS Configuration</h4>
        <button @click="showDnsHelp = false" class="text-blue-600 hover:text-blue-800">&times;</button>
      </div>
      
      <div class="dns-records space-y-4">
        <div class="dns-record">
          <h5 class="font-medium text-sm text-blue-700">Required DNS Records</h5>
          <div class="mt-2 font-mono text-sm bg-white p-3 rounded border">
            <div class="grid grid-cols-4 gap-2 mb-2 font-bold text-xs">
              <span>Type</span>
              <span>Name</span>
              <span>Value</span>
              <span>TTL</span>
            </div>
            <div class="grid grid-cols-4 gap-2 text-xs">
              <span>CNAME</span>
              <span>{{ selectedDomainForDns || 'your-domain.com' }}</span>
              <span>{{ platformDomain }}</span>
              <span>300</span>
            </div>
            <div class="grid grid-cols-4 gap-2 text-xs mt-1">
              <span>TXT</span>
              <span>_coolify-verify.{{ selectedDomainForDns || 'your-domain.com' }}</span>
              <span>{{ verificationToken }}</span>
              <span>300</span>
            </div>
          </div>
        </div>
        
        <div class="dns-steps">
          <h5 class="font-medium text-sm text-blue-700">Setup Steps</h5>
          <ol class="list-decimal list-inside text-sm mt-2 space-y-1">
            <li>Log in to your domain registrar's DNS management panel</li>
            <li>Add the CNAME record pointing your domain to our platform</li>
            <li>Add the TXT record for domain verification</li>
            <li>Wait for DNS propagation (usually 5-15 minutes)</li>
            <li>Click "Verify" to complete the setup</li>
          </ol>
        </div>
      </div>
    </div>

    <!-- Bulk Domain Management -->
    <div class="bulk-management mt-6 p-4 border border-gray-200 rounded">
      <h4 class="font-medium mb-3">Bulk Management</h4>
      
      <div class="bulk-actions space-y-3">
        <div class="bulk-import">
          <label class="block text-sm font-medium mb-2">Import from CSV/Text</label>
          <textarea 
            v-model="bulkDomains"
            rows="4"
            placeholder="Enter domains separated by commas or new lines:&#10;example1.com&#10;example2.com&#10;example3.com"
            class="textarea w-full text-sm"
          ></textarea>
          <div class="flex gap-2 mt-2">
            <button 
              @click="importBulkDomains"
              class="btn btn-sm"
              :disabled="!bulkDomains.trim() || bulkImporting"
            >
              {{ bulkImporting ? 'Importing...' : 'Import Domains' }}
            </button>
            <button 
              @click="validateBulkDomains"
              class="btn btn-sm btn-secondary"
              :disabled="!bulkDomains.trim()"
            >
              Validate
            </button>
          </div>
        </div>
        
        <div v-if="bulkValidationResults.length > 0" class="bulk-results">
          <h5 class="font-medium text-sm mb-2">Validation Results</h5>
          <div class="max-h-32 overflow-y-auto text-sm">
            <div 
              v-for="(result, index) in bulkValidationResults" 
              :key="index"
              :class="result.valid ? 'text-green-600' : 'text-red-600'"
              class="py-1"
            >
              {{ result.domain }}: {{ result.valid ? 'Valid' : result.error }}
            </div>
          </div>
        </div>
        
        <div class="bulk-export">
          <button 
            @click="exportDomains"
            class="btn btn-sm btn-secondary"
            :disabled="domains.length === 0"
          >
            Export Current Domains
          </button>
        </div>
      </div>
    </div>

    <!-- Domain Analytics -->
    <div v-if="domainAnalytics" class="domain-analytics mt-6 p-4 border border-gray-200 rounded">
      <h4 class="font-medium mb-3">Domain Analytics</h4>
      
      <div class="analytics-grid grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div class="analytic-item">
          <div class="analytic-value text-lg font-bold text-blue-600">{{ domainAnalytics.total }}</div>
          <div class="analytic-label">Total Domains</div>
        </div>
        <div class="analytic-item">
          <div class="analytic-value text-lg font-bold text-green-600">{{ domainAnalytics.verified }}</div>
          <div class="analytic-label">Verified</div>
        </div>
        <div class="analytic-item">
          <div class="analytic-value text-lg font-bold text-yellow-600">{{ domainAnalytics.pending }}</div>
          <div class="analytic-label">Pending</div>
        </div>
        <div class="analytic-item">
          <div class="analytic-value text-lg font-bold text-red-600">{{ domainAnalytics.failed }}</div>
          <div class="analytic-label">Failed</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DomainManager',
  props: {
    customDomains: {
      type: Array,
      default: () => []
    }
  },
  emits: ['domains-updated'],
  data() {
    return {
      domains: [...this.customDomains],
      newDomain: '',
      domainError: '',
      domainSuccess: '',
      adding: false,
      removing: false,
      verifying: false,
      checking: false,
      showDnsHelp: false,
      selectedDomainForDns: '',
      verificationToken: '',
      platformDomain: 'your-platform.com', // This should come from config
      bulkDomains: '',
      bulkImporting: false,
      bulkValidationResults: [],
      domainAnalytics: null
    }
  },
  computed: {
    isValidDomain() {
      return this.newDomain && this.validateDomainFormat(this.newDomain) && !this.domainExists(this.newDomain)
    }
  },
  watch: {
    customDomains(newVal) {
      this.domains = [...newVal]
      this.updateAnalytics()
    },
    domains: {
      handler() {
        this.updateAnalytics()
        this.$emit('domains-updated', this.domains)
      },
      deep: true
    }
  },
  mounted() {
    this.updateAnalytics()
    this.loadPlatformConfig()
  },
  methods: {
    validateDomain() {
      this.domainError = ''
      
      if (!this.newDomain) {
        return
      }
      
      if (!this.validateDomainFormat(this.newDomain)) {
        this.domainError = 'Please enter a valid domain name'
        return
      }
      
      if (this.domainExists(this.newDomain)) {
        this.domainError = 'This domain is already added'
        return
      }
    },

    validateDomainFormat(domain) {
      // Basic domain validation
      const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/
      return domainRegex.test(domain)
    },

    domainExists(domain) {
      return this.domains.some(d => 
        (typeof d === 'string' ? d : d.domain) === domain
      )
    },

    async addDomain() {
      if (!this.isValidDomain) return

      this.adding = true
      this.domainError = ''
      this.domainSuccess = ''

      try {
        const response = await fetch('/internal-api/white-label/domains', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ domain: this.newDomain })
        })

        if (response.ok) {
          const data = await response.json()
          this.domains.push({
            domain: this.newDomain,
            status: 'pending',
            added_at: new Date().toISOString(),
            verification_token: data.verification_token
          })
          
          this.domainSuccess = `Domain ${this.newDomain} added successfully`
          this.newDomain = ''
          this.showDnsSetupHelp(this.newDomain, data.verification_token)
        } else {
          const error = await response.json()
          this.domainError = error.message || 'Failed to add domain'
        }
      } catch (error) {
        this.domainError = 'Failed to add domain'
      } finally {
        this.adding = false
      }
    },

    async removeDomain(index) {
      const domain = this.domains[index]
      const domainName = typeof domain === 'string' ? domain : domain.domain
      
      if (!confirm(`Are you sure you want to remove ${domainName}?`)) {
        return
      }

      this.removing = index
      
      try {
        const response = await fetch('/internal-api/white-label/domains', {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ domain: domainName })
        })

        if (response.ok) {
          this.domains.splice(index, 1)
          this.domainSuccess = `Domain ${domainName} removed successfully`
        } else {
          const error = await response.json()
          this.domainError = error.message || 'Failed to remove domain'
        }
      } catch (error) {
        this.domainError = 'Failed to remove domain'
      } finally {
        this.removing = false
      }
    },

    async verifyDomain(domainName, index) {
      this.verifying = index

      try {
        const response = await fetch('/internal-api/white-label/domains/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ domain: domainName })
        })

        if (response.ok) {
          const data = await response.json()
          if (typeof this.domains[index] === 'object') {
            this.domains[index].status = data.verified ? 'verified' : 'failed'
            this.domains[index].verified_at = data.verified ? new Date().toISOString() : null
          }
          
          this.domainSuccess = data.verified 
            ? `Domain ${domainName} verified successfully`
            : `Domain verification failed: ${data.error}`
        } else {
          const error = await response.json()
          this.domainError = error.message || 'Failed to verify domain'
        }
      } catch (error) {
        this.domainError = 'Failed to verify domain'
      } finally {
        this.verifying = false
      }
    },

    async checkDnsStatus(domainName, index) {
      this.checking = index

      try {
        const response = await fetch(`/internal-api/white-label/domains/dns-status?domain=${encodeURIComponent(domainName)}`)
        
        if (response.ok) {
          const data = await response.json()
          this.domainSuccess = `DNS Status: ${data.status} - ${data.message}`
        } else {
          this.domainError = 'Failed to check DNS status'
        }
      } catch (error) {
        this.domainError = 'Failed to check DNS status'
      } finally {
        this.checking = false
      }
    },

    showDnsSetupHelp(domain, token) {
      this.selectedDomainForDns = domain
      this.verificationToken = token
      this.showDnsHelp = true
    },

    validateBulkDomains() {
      const domains = this.parseBulkDomains()
      this.bulkValidationResults = domains.map(domain => ({
        domain,
        valid: this.validateDomainFormat(domain) && !this.domainExists(domain),
        error: !this.validateDomainFormat(domain) 
          ? 'Invalid format' 
          : this.domainExists(domain) 
            ? 'Already exists' 
            : null
      }))
    },

    async importBulkDomains() {
      const domains = this.parseBulkDomains()
      const validDomains = domains.filter(domain => 
        this.validateDomainFormat(domain) && !this.domainExists(domain)
      )

      if (validDomains.length === 0) {
        this.domainError = 'No valid domains to import'
        return
      }

      this.bulkImporting = true

      try {
        const response = await fetch('/internal-api/white-label/domains/bulk', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ domains: validDomains })
        })

        if (response.ok) {
          const data = await response.json()
          data.domains.forEach(domainData => {
            this.domains.push({
              domain: domainData.domain,
              status: 'pending',
              added_at: new Date().toISOString(),
              verification_token: domainData.verification_token
            })
          })
          
          this.domainSuccess = `${validDomains.length} domains imported successfully`
          this.bulkDomains = ''
          this.bulkValidationResults = []
        } else {
          const error = await response.json()
          this.domainError = error.message || 'Failed to import domains'
        }
      } catch (error) {
        this.domainError = 'Failed to import domains'
      } finally {
        this.bulkImporting = false
      }
    },

    parseBulkDomains() {
      return this.bulkDomains
        .split(/[,\n\r]+/)
        .map(domain => domain.trim())
        .filter(domain => domain.length > 0)
    },

    exportDomains() {
      const domainList = this.domains.map(domain => 
        typeof domain === 'string' ? domain : domain.domain
      ).join('\n')
      
      const blob = new Blob([domainList], { type: 'text/plain' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `domains-${Date.now()}.txt`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    },

    updateAnalytics() {
      const total = this.domains.length
      const verified = this.domains.filter(d => 
        typeof d === 'object' && d.status === 'verified'
      ).length
      const pending = this.domains.filter(d => 
        typeof d === 'string' || d.status === 'pending'
      ).length
      const failed = this.domains.filter(d => 
        typeof d === 'object' && d.status === 'failed'
      ).length

      this.domainAnalytics = { total, verified, pending, failed }
    },

    async loadPlatformConfig() {
      try {
        const response = await fetch('/internal-api/platform-config')
        if (response.ok) {
          const config = await response.json()
          this.platformDomain = config.domain || 'your-platform.com'
        }
      } catch (error) {
        console.warn('Failed to load platform config')
      }
    },

    statusClass(status) {
      const classes = {
        verified: 'bg-green-100 text-green-800',
        pending: 'bg-yellow-100 text-yellow-800',
        failed: 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    },

    formatDate(dateString) {
      if (!dateString) return ''
      return new Date(dateString).toLocaleDateString()
    }
  }
}
</script>

<style scoped>
.domain-item {
  @apply flex items-center justify-between p-3 border border-gray-200 rounded;
}

.domain-info {
  @apply flex-1;
}

.domain-name {
  @apply font-medium flex items-center gap-2;
}

.domain-details {
  @apply mt-1;
}

.domain-actions {
  @apply flex gap-2;
}

.status-badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
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

.btn-secondary {
  @apply bg-gray-200 hover:bg-gray-300;
}

.btn-danger {
  @apply bg-red-600 text-white border-red-600 hover:bg-red-700;
}

.btn:disabled {
  @apply opacity-50 cursor-not-allowed;
}

.input, .textarea {
  @apply border border-gray-300 rounded px-3 py-2;
}

.dns-record {
  @apply text-sm;
}

.analytic-item {
  @apply text-center;
}

.analytic-value {
  @apply font-bold text-lg;
}

.analytic-label {
  @apply text-gray-600 text-sm;
}

.dark .domain-item,
.dark .bulk-management,
.dark .domain-analytics,
.dark .dns-help {
  @apply border-gray-600;
}

.dark .input,
.dark .textarea {
  @apply bg-gray-700 border-gray-600 text-white;
}

.dark .btn {
  @apply border-gray-600 hover:bg-gray-700;
}

.dark .dns-help {
  @apply bg-gray-800;
}

.dark .analytic-label {
  @apply text-gray-400;
}
</style>