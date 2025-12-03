<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Upgrade License</h3>
        <button @click="$emit('close')" class="modal-close">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- Current License Info -->
        <div class="current-license mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
          <h4 class="font-medium mb-3">Current License</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
              <span class="text-gray-600 dark:text-gray-400">Current Tier:</span>
              <span :class="currentTierClass" class="badge ml-2">{{ license.license_tier.toUpperCase() }}</span>
            </div>
            <div>
              <span class="text-gray-600 dark:text-gray-400">License Type:</span>
              <div class="capitalize">{{ license.license_type }}</div>
            </div>
            <div>
              <span class="text-gray-600 dark:text-gray-400">Expires:</span>
              <div>{{ license.expires_at ? formatDate(license.expires_at) : 'Never' }}</div>
            </div>
          </div>
        </div>

        <!-- Upgrade Options -->
        <div class="upgrade-options mb-6">
          <h4 class="font-medium mb-4">Choose Your New License Tier</h4>
          <div class="tier-comparison grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Basic Tier -->
            <div 
              class="tier-card"
              :class="{ 
                'tier-current': license.license_tier === 'basic',
                'tier-selected': form.new_tier === 'basic',
                'tier-disabled': !canUpgradeTo('basic')
              }"
              @click="selectTier('basic')"
            >
              <div class="tier-header">
                <h5 class="tier-name">Basic</h5>
                <div class="tier-price">$99<span class="tier-period">/month</span></div>
                <div v-if="license.license_tier === 'basic'" class="tier-current-badge">
                  Current Plan
                </div>
              </div>
              
              <div class="tier-features">
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Application Deployment
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Database Management
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  SSL Certificates
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  API Access
                </div>
                <div class="feature-limits">
                  <div class="text-sm text-gray-600">
                    • Up to 5 users<br>
                    • Up to 3 servers<br>
                    • Up to 10 applications
                  </div>
                </div>
              </div>
            </div>

            <!-- Professional Tier -->
            <div 
              class="tier-card tier-popular"
              :class="{ 
                'tier-current': license.license_tier === 'professional',
                'tier-selected': form.new_tier === 'professional',
                'tier-disabled': !canUpgradeTo('professional')
              }"
              @click="selectTier('professional')"
            >
              <div class="tier-popular-badge">Most Popular</div>
              <div class="tier-header">
                <h5 class="tier-name">Professional</h5>
                <div class="tier-price">$299<span class="tier-period">/month</span></div>
                <div v-if="license.license_tier === 'professional'" class="tier-current-badge">
                  Current Plan
                </div>
              </div>
              
              <div class="tier-features">
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Everything in Basic
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Server Provisioning
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Terraform Integration
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  White-Label Branding
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Organization Hierarchy
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Multi-Factor Authentication
                </div>
                <div class="feature-limits">
                  <div class="text-sm text-gray-600">
                    • Up to 25 users<br>
                    • Up to 15 servers<br>
                    • Up to 50 applications
                  </div>
                </div>
              </div>
            </div>

            <!-- Enterprise Tier -->
            <div 
              class="tier-card"
              :class="{ 
                'tier-current': license.license_tier === 'enterprise',
                'tier-selected': form.new_tier === 'enterprise',
                'tier-disabled': !canUpgradeTo('enterprise')
              }"
              @click="selectTier('enterprise')"
            >
              <div class="tier-header">
                <h5 class="tier-name">Enterprise</h5>
                <div class="tier-price">$999<span class="tier-period">/month</span></div>
                <div v-if="license.license_tier === 'enterprise'" class="tier-current-badge">
                  Current Plan
                </div>
              </div>
              
              <div class="tier-features">
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Everything in Professional
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Multi-Cloud Support
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Payment Processing
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Domain Management
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Advanced RBAC
                </div>
                <div class="feature-item">
                  <i class="fas fa-check text-green-500"></i>
                  Compliance Reporting
                </div>
                <div class="feature-limits">
                  <div class="text-sm text-gray-600">
                    • Unlimited users<br>
                    • Unlimited servers<br>
                    • Unlimited applications
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upgrade Form -->
        <form v-if="form.new_tier && canUpgradeTo(form.new_tier)" @submit.prevent="upgradeLicense" class="space-y-6">
          <!-- Upgrade Type -->
          <div>
            <label class="form-label">Upgrade Type</label>
            <div class="radio-group">
              <label class="radio-option">
                <input v-model="form.upgrade_type" type="radio" value="immediate" />
                <span class="radio-label">
                  <strong>Immediate Upgrade</strong>
                  <span class="text-sm text-gray-600">Upgrade now and pay the prorated difference</span>
                </span>
              </label>
              <label class="radio-option">
                <input v-model="form.upgrade_type" type="radio" value="next_billing" />
                <span class="radio-label">
                  <strong>Upgrade at Next Billing Cycle</strong>
                  <span class="text-sm text-gray-600">Upgrade when your current license expires</span>
                </span>
              </label>
            </div>
          </div>

          <!-- Payment Method -->
          <div v-if="form.upgrade_type === 'immediate'">
            <label class="form-label">Payment Method</label>
            <div class="payment-methods space-y-3">
              <label class="payment-option">
                <input v-model="form.payment_method" type="radio" value="credit_card" />
                <div class="payment-option-content">
                  <div class="flex items-center">
                    <i class="fas fa-credit-card mr-2"></i>
                    <strong>Credit Card</strong>
                  </div>
                  <div class="text-sm text-gray-600">Pay with credit or debit card</div>
                </div>
              </label>
              
              <label class="payment-option">
                <input v-model="form.payment_method" type="radio" value="bank_transfer" />
                <div class="payment-option-content">
                  <div class="flex items-center">
                    <i class="fas fa-university mr-2"></i>
                    <strong>Bank Transfer</strong>
                  </div>
                  <div class="text-sm text-gray-600">Pay via bank transfer</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Upgrade Summary -->
          <div class="upgrade-summary p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <h4 class="font-medium mb-3">Upgrade Summary</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span>Current Tier:</span>
                <span class="capitalize">{{ license.license_tier }}</span>
              </div>
              <div class="flex justify-between">
                <span>New Tier:</span>
                <span class="capitalize">{{ form.new_tier }}</span>
              </div>
              <div class="flex justify-between">
                <span>Upgrade Type:</span>
                <span>{{ form.upgrade_type === 'immediate' ? 'Immediate' : 'Next Billing Cycle' }}</span>
              </div>
              <div v-if="form.upgrade_type === 'immediate'" class="flex justify-between">
                <span>Prorated Amount:</span>
                <span>${{ calculateProratedCost() }}</span>
              </div>
              <div class="flex justify-between">
                <span>New Monthly Cost:</span>
                <span>${{ getNewMonthlyCost() }}</span>
              </div>
              <div v-if="form.upgrade_type === 'immediate'" class="flex justify-between font-medium text-lg pt-2 border-t border-blue-200 dark:border-blue-700">
                <span>Total Due Today:</span>
                <span>${{ calculateProratedCost() }}</span>
              </div>
            </div>
          </div>

          <!-- Terms and Conditions -->
          <div class="terms-section">
            <label class="flex items-start space-x-3">
              <input 
                v-model="form.accept_terms" 
                type="checkbox" 
                required
                class="mt-1"
              />
              <span class="text-sm">
                I agree to the 
                <a href="/terms" target="_blank" class="text-blue-600 hover:text-blue-800">Terms of Service</a>
                and understand that this upgrade will change my billing amount.
              </span>
            </label>
          </div>

          <!-- Form Actions -->
          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="$emit('close')" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" :disabled="submitting || !form.accept_terms" class="btn btn-primary">
              {{ submitting ? 'Processing Upgrade...' : 'Upgrade License' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LicenseUpgrade',
  props: {
    license: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'license-upgraded'],
  data() {
    return {
      form: {
        new_tier: '',
        upgrade_type: 'immediate',
        payment_method: 'credit_card',
        accept_terms: false
      },
      tierPricing: {
        basic: 99,
        professional: 299,
        enterprise: 999
      },
      submitting: false
    }
  },
  computed: {
    currentTierClass() {
      const tierClasses = {
        basic: 'bg-blue-100 text-blue-800',
        professional: 'bg-purple-100 text-purple-800',
        enterprise: 'bg-indigo-100 text-indigo-800'
      }
      return tierClasses[this.license.license_tier] || 'bg-gray-100 text-gray-800'
    }
  },
  methods: {
    canUpgradeTo(tier) {
      const tierHierarchy = ['basic', 'professional', 'enterprise']
      const currentIndex = tierHierarchy.indexOf(this.license.license_tier)
      const targetIndex = tierHierarchy.indexOf(tier)
      return targetIndex > currentIndex
    },

    selectTier(tier) {
      if (this.canUpgradeTo(tier)) {
        this.form.new_tier = tier
      }
    },

    getNewMonthlyCost() {
      return this.tierPricing[this.form.new_tier] || 0
    },

    calculateProratedCost() {
      if (this.form.upgrade_type !== 'immediate') return 0
      
      const currentCost = this.tierPricing[this.license.license_tier] || 0
      const newCost = this.tierPricing[this.form.new_tier] || 0
      const difference = newCost - currentCost
      
      // Calculate prorated amount based on remaining days in current billing cycle
      // For simplicity, assuming 30 days in a month and 15 days remaining
      const remainingDays = 15 // This would be calculated based on actual billing cycle
      const proratedAmount = (difference / 30) * remainingDays
      
      return Math.round(proratedAmount)
    },

    async upgradeLicense() {
      try {
        this.submitting = true
        
        const upgradeData = {
          ...this.form,
          current_tier: this.license.license_tier,
          prorated_cost: this.calculateProratedCost(),
          new_monthly_cost: this.getNewMonthlyCost()
        }
        
        const response = await fetch(`/internal-api/licenses/${this.license.id}/upgrade`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(upgradeData)
        })

        if (response.ok) {
          this.$emit('license-upgraded')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to upgrade license')
        }
      } catch (error) {
        console.error('Error upgrading license:', error)
        alert('Failed to upgrade license')
      } finally {
        this.submitting = false
      }
    },

    formatDate(dateString) {
      if (!dateString) return ''
      return new Date(dateString).toLocaleDateString()
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

.tier-card {
  @apply relative border-2 border-gray-200 dark:border-gray-600 rounded-lg p-4 cursor-pointer transition-all duration-200 hover:shadow-md;
}

.tier-card.tier-popular {
  @apply border-blue-500 shadow-lg;
}

.tier-card.tier-selected {
  @apply border-blue-500 bg-blue-50 dark:bg-blue-900/20;
}

.tier-card.tier-current {
  @apply border-green-500 bg-green-50 dark:bg-green-900/20;
}

.tier-card.tier-disabled {
  @apply opacity-50 cursor-not-allowed;
}

.tier-popular-badge {
  @apply absolute -top-3 left-1/2 transform -translate-x-1/2 bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-medium;
}

.tier-current-badge {
  @apply bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium;
}

.tier-header {
  @apply text-center mb-4;
}

.tier-name {
  @apply text-xl font-bold text-gray-900 dark:text-gray-100;
}

.tier-price {
  @apply text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2;
}

.tier-period {
  @apply text-sm font-normal text-gray-600 dark:text-gray-400;
}

.tier-features {
  @apply space-y-2;
}

.feature-item {
  @apply flex items-center text-sm;
}

.feature-limits {
  @apply mt-3 pt-3 border-t border-gray-200 dark:border-gray-600;
}

.form-label {
  @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2;
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

.payment-methods {
  @apply border border-gray-200 dark:border-gray-600 rounded-lg p-4;
}

.payment-option {
  @apply flex items-start space-x-3 p-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700;
}

.payment-option input[type="radio"] {
  @apply mt-1;
}

.payment-option-content {
  @apply flex flex-col;
}

.badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.btn {
  @apply px-4 py-2 rounded font-medium cursor-pointer border-none transition-colors;
}

.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-secondary {
  @apply bg-gray-600 text-white hover:bg-gray-700;
}
</style>