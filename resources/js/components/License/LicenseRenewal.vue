<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Renew License</h3>
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
              <span class="text-gray-600 dark:text-gray-400">License Key:</span>
              <div class="font-mono">{{ formatLicenseKey(license.license_key) }}</div>
            </div>
            <div>
              <span class="text-gray-600 dark:text-gray-400">Current Expiration:</span>
              <div>{{ license.expires_at ? formatDate(license.expires_at) : 'Never' }}</div>
            </div>
            <div>
              <span class="text-gray-600 dark:text-gray-400">Status:</span>
              <span :class="licenseStatusClass" class="badge">{{ license.status }}</span>
            </div>
          </div>
        </div>

        <!-- Renewal Options -->
        <form @submit.prevent="renewLicense" class="space-y-6">
          <!-- Renewal Period -->
          <div>
            <label class="form-label">Renewal Period *</label>
            <div class="radio-group">
              <label class="radio-option">
                <input v-model="form.renewal_period" type="radio" value="1_month" />
                <span class="radio-label">
                  <strong>1 Month</strong>
                  <span class="text-sm text-gray-600">Extends license by 1 month</span>
                  <span class="text-sm font-medium text-green-600">${{ pricing.monthly }}</span>
                </span>
              </label>
              <label class="radio-option">
                <input v-model="form.renewal_period" type="radio" value="3_months" />
                <span class="radio-label">
                  <strong>3 Months</strong>
                  <span class="text-sm text-gray-600">Extends license by 3 months</span>
                  <span class="text-sm font-medium text-green-600">
                    ${{ pricing.quarterly }} 
                    <span class="text-xs text-gray-500">(Save {{ Math.round((1 - pricing.quarterly / (pricing.monthly * 3)) * 100) }}%)</span>
                  </span>
                </span>
              </label>
              <label class="radio-option">
                <input v-model="form.renewal_period" type="radio" value="1_year" />
                <span class="radio-label">
                  <strong>1 Year</strong>
                  <span class="text-sm text-gray-600">Extends license by 1 year</span>
                  <span class="text-sm font-medium text-green-600">
                    ${{ pricing.yearly }} 
                    <span class="text-xs text-gray-500">(Save {{ Math.round((1 - pricing.yearly / (pricing.monthly * 12)) * 100) }}%)</span>
                  </span>
                </span>
              </label>
              <label class="radio-option">
                <input v-model="form.renewal_period" type="radio" value="custom" />
                <span class="radio-label">
                  <strong>Custom Period</strong>
                  <span class="text-sm text-gray-600">Specify custom expiration date</span>
                </span>
              </label>
            </div>
          </div>

          <!-- Custom Expiration Date -->
          <div v-if="form.renewal_period === 'custom'">
            <label class="form-label">Custom Expiration Date *</label>
            <input 
              v-model="form.custom_expires_at" 
              type="datetime-local" 
              class="form-input"
              :min="minExpirationDate"
              required
            />
            <div class="form-help">
              Set a specific expiration date for the renewed license.
            </div>
          </div>

          <!-- Auto-Renewal Option -->
          <div class="auto-renewal-section p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
            <div class="flex items-start space-x-3">
              <input 
                v-model="form.auto_renewal" 
                type="checkbox" 
                id="auto-renewal"
                class="mt-1"
              />
              <div>
                <label for="auto-renewal" class="font-medium cursor-pointer">
                  Enable Auto-Renewal
                </label>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Automatically renew this license before it expires to ensure uninterrupted service.
                  You can cancel auto-renewal at any time.
                </p>
              </div>
            </div>
          </div>

          <!-- Payment Method -->
          <div>
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
                  <div class="text-sm text-gray-600">Pay via bank transfer (may take 2-3 business days)</div>
                </div>
              </label>
              
              <label class="payment-option">
                <input v-model="form.payment_method" type="radio" value="invoice" />
                <div class="payment-option-content">
                  <div class="flex items-center">
                    <i class="fas fa-file-invoice mr-2"></i>
                    <strong>Invoice</strong>
                  </div>
                  <div class="text-sm text-gray-600">Generate invoice for manual payment</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Renewal Summary -->
          <div class="renewal-summary p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <h4 class="font-medium mb-3">Renewal Summary</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span>Current Expiration:</span>
                <span>{{ license.expires_at ? formatDate(license.expires_at) : 'Never' }}</span>
              </div>
              <div class="flex justify-between">
                <span>New Expiration:</span>
                <span>{{ formatDate(newExpirationDate) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Extension Period:</span>
                <span>{{ formatRenewalPeriod(form.renewal_period) }}</span>
              </div>
              <div class="flex justify-between font-medium text-lg pt-2 border-t border-blue-200 dark:border-blue-700">
                <span>Total Cost:</span>
                <span>${{ calculateCost() }}</span>
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
                and 
                <a href="/privacy" target="_blank" class="text-blue-600 hover:text-blue-800">Privacy Policy</a>
              </span>
            </label>
          </div>

          <!-- Form Actions -->
          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="$emit('close')" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" :disabled="submitting || !form.accept_terms" class="btn btn-primary">
              {{ submitting ? 'Processing Renewal...' : 'Renew License' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LicenseRenewal',
  props: {
    license: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'license-renewed'],
  data() {
    return {
      form: {
        renewal_period: '1_year',
        custom_expires_at: '',
        auto_renewal: false,
        payment_method: 'credit_card',
        accept_terms: false
      },
      pricing: {
        monthly: 99,
        quarterly: 267, // 10% discount
        yearly: 950     // 20% discount
      },
      submitting: false
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
    minExpirationDate() {
      const now = new Date()
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
      return now.toISOString().slice(0, 16)
    },
    newExpirationDate() {
      if (this.form.renewal_period === 'custom') {
        return this.form.custom_expires_at
      }
      
      const currentExpiration = this.license.expires_at ? new Date(this.license.expires_at) : new Date()
      const baseDate = currentExpiration > new Date() ? currentExpiration : new Date()
      
      switch (this.form.renewal_period) {
        case '1_month':
          return new Date(baseDate.getTime() + 30 * 24 * 60 * 60 * 1000).toISOString()
        case '3_months':
          return new Date(baseDate.getTime() + 90 * 24 * 60 * 60 * 1000).toISOString()
        case '1_year':
          return new Date(baseDate.getTime() + 365 * 24 * 60 * 60 * 1000).toISOString()
        default:
          return baseDate.toISOString()
      }
    }
  },
  methods: {
    async renewLicense() {
      try {
        this.submitting = true
        
        const renewalData = {
          ...this.form,
          new_expires_at: this.newExpirationDate,
          cost: this.calculateCost()
        }
        
        const response = await fetch(`/internal-api/licenses/${this.license.id}/renew`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(renewalData)
        })

        if (response.ok) {
          this.$emit('license-renewed')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to renew license')
        }
      } catch (error) {
        console.error('Error renewing license:', error)
        alert('Failed to renew license')
      } finally {
        this.submitting = false
      }
    },

    calculateCost() {
      switch (this.form.renewal_period) {
        case '1_month':
          return this.pricing.monthly
        case '3_months':
          return this.pricing.quarterly
        case '1_year':
          return this.pricing.yearly
        case 'custom':
          // Calculate based on days
          const currentExpiration = this.license.expires_at ? new Date(this.license.expires_at) : new Date()
          const baseDate = currentExpiration > new Date() ? currentExpiration : new Date()
          const customDate = new Date(this.form.custom_expires_at)
          const days = Math.ceil((customDate - baseDate) / (1000 * 60 * 60 * 24))
          const dailyRate = this.pricing.yearly / 365
          return Math.round(days * dailyRate)
        default:
          return 0
      }
    },

    formatRenewalPeriod(period) {
      const periods = {
        '1_month': '1 Month',
        '3_months': '3 Months',
        '1_year': '1 Year',
        'custom': 'Custom Period'
      }
      return periods[period] || period
    },

    formatLicenseKey(key) {
      if (!key) return ''
      return key.replace(/(.{4})/g, '$1-').slice(0, -1)
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
  @apply bg-white dark:bg-gray-800 rounded-lg max-w-3xl w-full max-h-[90vh] overflow-y-auto;
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

.form-label {
  @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2;
}

.form-input {
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