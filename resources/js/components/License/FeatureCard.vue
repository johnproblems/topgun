<template>
  <div class="feature-card" :class="cardClass">
    <div class="flex items-start justify-between mb-3">
      <div class="feature-icon" :class="iconClass">
        <i :class="feature.icon"></i>
      </div>
      <div class="feature-status">
        <i v-if="enabled" class="fas fa-check-circle text-green-500"></i>
        <i v-else-if="canUpgrade" class="fas fa-lock text-gray-400"></i>
        <i v-else class="fas fa-times-circle text-red-400"></i>
      </div>
    </div>
    
    <h4 class="feature-name">{{ feature.name }}</h4>
    <p class="feature-description">{{ feature.description }}</p>
    
    <div class="feature-footer">
      <div class="flex items-center justify-between">
        <span class="feature-tier">
          {{ formatTier(feature.requiredTier) }}+
        </span>
        <div class="feature-actions">
          <button 
            v-if="!enabled && canUpgrade"
            @click="$emit('upgrade-requested', feature)"
            class="btn btn-xs btn-primary"
          >
            Upgrade
          </button>
          <span v-else-if="enabled" class="text-green-600 text-sm font-medium">
            Active
          </span>
          <span v-else class="text-gray-400 text-sm">
            Not Available
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FeatureCard',
  props: {
    feature: {
      type: Object,
      required: true
    },
    enabled: {
      type: Boolean,
      default: false
    },
    licenseTier: {
      type: String,
      required: true
    }
  },
  emits: ['upgrade-requested'],
  computed: {
    cardClass() {
      if (this.enabled) {
        return 'border-green-200 bg-green-50 dark:bg-green-900/20'
      } else if (this.canUpgrade) {
        return 'border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20'
      } else {
        return 'border-gray-200 bg-gray-50 dark:bg-gray-800'
      }
    },
    iconClass() {
      if (this.enabled) {
        return 'text-green-600 bg-green-100'
      } else if (this.canUpgrade) {
        return 'text-yellow-600 bg-yellow-100'
      } else {
        return 'text-gray-400 bg-gray-100'
      }
    },
    canUpgrade() {
      const tierHierarchy = ['basic', 'professional', 'enterprise']
      const currentTierIndex = tierHierarchy.indexOf(this.licenseTier)
      const requiredTierIndex = tierHierarchy.indexOf(this.feature.requiredTier)
      
      return requiredTierIndex > currentTierIndex
    }
  },
  methods: {
    formatTier(tier) {
      return tier.charAt(0).toUpperCase() + tier.slice(1)
    }
  }
}
</script>

<style scoped>
.feature-card {
  @apply p-4 rounded-lg border transition-all duration-200 hover:shadow-md;
}

.feature-icon {
  @apply w-10 h-10 rounded-lg flex items-center justify-center text-lg;
}

.feature-status {
  @apply text-lg;
}

.feature-name {
  @apply font-medium text-gray-900 dark:text-gray-100 mb-2;
}

.feature-description {
  @apply text-sm text-gray-600 dark:text-gray-400 mb-4 leading-relaxed;
}

.feature-footer {
  @apply pt-3 border-t border-gray-200 dark:border-gray-700;
}

.feature-tier {
  @apply text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide;
}

.btn {
  @apply px-2 py-1 rounded font-medium cursor-pointer border-none transition-colors;
}

.btn-xs {
  @apply text-xs px-2 py-1;
}

.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700;
}
</style>