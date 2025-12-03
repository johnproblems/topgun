<template>
  <div class="usage-monitoring box">
    <div class="flex justify-between items-center mb-4">
      <div>
        <h2>Usage Monitoring</h2>
        <div class="subtitle">Track resource usage against license limits.</div>
      </div>
      <div class="text-sm opacity-75">
        Last updated: {{ formatDate(usageStats.last_validated) }}
      </div>
    </div>

    <!-- Usage Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div 
        v-for="(stat, type) in usageStats.statistics" 
        :key="type"
        class="usage-card"
        :class="getUsageCardClass(stat)"
      >
        <div class="flex justify-between items-start mb-2">
          <h4 class="font-medium capitalize">{{ formatUsageType(type) }}</h4>
          <div class="usage-icon">
            <i :class="getUsageIcon(type)"></i>
          </div>
        </div>
        
        <div class="text-2xl font-bold mb-1">
          {{ stat.current }}
          <span v-if="!stat.unlimited" class="text-sm font-normal opacity-75">
            / {{ stat.limit }}
          </span>
          <span v-else class="text-sm font-normal opacity-75">
            (Unlimited)
          </span>
        </div>
        
        <div class="flex items-center justify-between">
          <div class="text-sm">
            <span v-if="!stat.unlimited" :class="getPercentageClass(stat.percentage)">
              {{ stat.percentage }}% used
            </span>
            <span v-else class="text-green-600">
              No limit
            </span>
          </div>
          <div v-if="!stat.unlimited && stat.remaining !== null" class="text-sm opacity-75">
            {{ stat.remaining }} left
          </div>
        </div>
        
        <!-- Progress Bar -->
        <div v-if="!stat.unlimited" class="w-full bg-gray-200 rounded-full h-2 mt-2">
          <div 
            class="h-2 rounded-full transition-all duration-300"
            :class="getProgressBarClass(stat.percentage)"
            :style="{ width: Math.min(stat.percentage, 100) + '%' }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Violations Alert -->
    <div v-if="!usageStats.within_limits" class="alert alert-danger mb-4">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <i class="fas fa-exclamation-triangle text-red-500"></i>
        </div>
        <div class="ml-3">
          <h4 class="font-medium text-red-800">License Limits Exceeded</h4>
          <div class="mt-2 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
              <li v-for="violation in usageStats.violations" :key="violation.type">
                {{ violation.message }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Expiration Warning -->
    <div v-if="expirationWarning" class="alert alert-warning mb-4">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <i class="fas fa-clock text-yellow-500"></i>
        </div>
        <div class="ml-3">
          <h4 class="font-medium text-yellow-800">License Expiration Warning</h4>
          <div class="mt-1 text-sm text-yellow-700">
            {{ expirationWarning }}
          </div>
        </div>
      </div>
    </div>

    <!-- Usage Trends Chart -->
    <div class="mt-6">
      <h3 class="font-medium mb-4">Usage Trends</h3>
      <div class="usage-chart-container">
        <canvas ref="usageChart" class="usage-chart"></canvas>
      </div>
    </div>

    <!-- Usage History Table -->
    <div class="mt-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-medium">Recent Usage History</h3>
        <button @click="exportUsageData" class="btn btn-sm btn-secondary">
          Export Data
        </button>
      </div>
      
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Date
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Users
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Servers
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Applications
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Domains
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="record in usageHistory" :key="record.date">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                {{ formatDate(record.date) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                {{ record.users }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                {{ record.servers }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                {{ record.applications }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                {{ record.domains }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="record.within_limits ? 'badge-success' : 'badge-danger'" class="badge">
                  {{ record.within_limits ? 'Within Limits' : 'Over Limits' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UsageMonitoring',
  props: {
    license: {
      type: Object,
      required: true
    },
    usageStats: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      usageHistory: [],
      chart: null
    }
  },
  computed: {
    expirationWarning() {
      if (!this.usageStats.expires_at) return null
      
      const daysUntilExpiration = this.usageStats.days_until_expiration
      if (daysUntilExpiration === null) return null
      
      if (daysUntilExpiration <= 0) {
        return 'Your license has expired. Please renew to continue using all features.'
      } else if (daysUntilExpiration <= 7) {
        return `Your license expires in ${daysUntilExpiration} day${daysUntilExpiration === 1 ? '' : 's'}. Please renew soon.`
      } else if (daysUntilExpiration <= 30) {
        return `Your license expires in ${daysUntilExpiration} days. Consider renewing to avoid service interruption.`
      }
      
      return null
    }
  },
  async mounted() {
    await this.loadUsageHistory()
    this.initializeChart()
  },
  beforeUnmount() {
    if (this.chart) {
      this.chart.destroy()
    }
  },
  methods: {
    async loadUsageHistory() {
      try {
        const response = await fetch(`/internal-api/licenses/${this.license.id}/usage-history`, {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          }
        })
        
        if (response.ok) {
          const data = await response.json()
          this.usageHistory = data.history || []
        }
      } catch (error) {
        console.error('Error loading usage history:', error)
      }
    },

    initializeChart() {
      // This would integrate with Chart.js or similar library
      // For now, we'll just prepare the canvas
      const canvas = this.$refs.usageChart
      if (!canvas) return

      // Mock chart implementation - in real implementation, use Chart.js
      const ctx = canvas.getContext('2d')
      ctx.fillStyle = '#e5e7eb'
      ctx.fillRect(0, 0, canvas.width, canvas.height)
      ctx.fillStyle = '#374151'
      ctx.font = '14px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText('Usage trends chart would be rendered here', canvas.width / 2, canvas.height / 2)
    },

    getUsageCardClass(stat) {
      if (stat.unlimited) return 'border-green-200 bg-green-50'
      if (stat.percentage >= 90) return 'border-red-200 bg-red-50'
      if (stat.percentage >= 75) return 'border-yellow-200 bg-yellow-50'
      return 'border-gray-200 bg-white'
    },

    getUsageIcon(type) {
      const icons = {
        users: 'fas fa-users',
        servers: 'fas fa-server',
        applications: 'fas fa-rocket',
        domains: 'fas fa-globe',
        cloud_providers: 'fas fa-cloud'
      }
      return icons[type] || 'fas fa-chart-bar'
    },

    getPercentageClass(percentage) {
      if (percentage >= 90) return 'text-red-600 font-medium'
      if (percentage >= 75) return 'text-yellow-600 font-medium'
      return 'text-gray-600'
    },

    getProgressBarClass(percentage) {
      if (percentage >= 90) return 'bg-red-500'
      if (percentage >= 75) return 'bg-yellow-500'
      return 'bg-green-500'
    },

    formatUsageType(type) {
      return type.replace(/_/g, ' ')
    },

    formatDate(dateString) {
      if (!dateString) return ''
      return new Date(dateString).toLocaleDateString()
    },

    async exportUsageData() {
      try {
        const response = await fetch(`/internal-api/licenses/${this.license.id}/usage-export`, {
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
          a.download = `usage-data-${this.license.license_key}-${new Date().toISOString().split('T')[0]}.csv`
          document.body.appendChild(a)
          a.click()
          window.URL.revokeObjectURL(url)
          document.body.removeChild(a)
        }
      } catch (error) {
        console.error('Error exporting usage data:', error)
      }
    }
  }
}
</script>

<style scoped>
.usage-card {
  @apply p-4 rounded-lg border;
}

.usage-icon {
  @apply w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-600;
}

.usage-chart-container {
  @apply h-64 bg-gray-50 rounded-lg p-4;
}

.usage-chart {
  @apply w-full h-full;
}

.alert {
  @apply p-4 rounded-lg;
}

.alert-danger {
  @apply bg-red-50 border border-red-200;
}

.alert-warning {
  @apply bg-yellow-50 border border-yellow-200;
}

.badge-success {
  @apply bg-green-100 text-green-800;
}

.badge-danger {
  @apply bg-red-100 text-red-800;
}
</style>