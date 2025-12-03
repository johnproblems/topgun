<template>
  <div class="feature-toggles box">
    <div class="flex justify-between items-center mb-4">
      <div>
        <h2>License Features</h2>
        <div class="subtitle">Available features based on your current license.</div>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-sm opacity-75">License Tier:</span>
        <span :class="licenseTierClass" class="badge">
          {{ license.license_tier.toUpperCase() }}
        </span>
      </div>
    </div>

    <!-- Feature Categories -->
    <div class="space-y-6">
      <!-- Core Platform Features -->
      <div class="feature-category">
        <h3 class="feature-category-title">
          <i class="fas fa-cogs mr-2"></i>
          Core Platform
        </h3>
        <div class="feature-grid">
          <FeatureCard
            v-for="feature in coreFeatures"
            :key="feature.key"
            :feature="feature"
            :enabled="hasFeature(feature.key)"
            :license-tier="license.license_tier"
          />
        </div>
      </div>

      <!-- Infrastructure Features -->
      <div class="feature-category">
        <h3 class="feature-category-title">
          <i class="fas fa-server mr-2"></i>
          Infrastructure Management
        </h3>
        <div class="feature-grid">
          <FeatureCard
            v-for="feature in infrastructureFeatures"
            :key="feature.key"
            :feature="feature"
            :enabled="hasFeature(feature.key)"
            :license-tier="license.license_tier"
          />
        </div>
      </div>

      <!-- Enterprise Features -->
      <div class="feature-category">
        <h3 class="feature-category-title">
          <i class="fas fa-building mr-2"></i>
          Enterprise & White-Label
        </h3>
        <div class="feature-grid">
          <FeatureCard
            v-for="feature in enterpriseFeatures"
            :key="feature.key"
            :feature="feature"
            :enabled="hasFeature(feature.key)"
            :license-tier="license.license_tier"
          />
        </div>
      </div>

      <!-- Integration Features -->
      <div class="feature-category">
        <h3 class="feature-category-title">
          <i class="fas fa-plug mr-2"></i>
          Integrations & APIs
        </h3>
        <div class="feature-grid">
          <FeatureCard
            v-for="feature in integrationFeatures"
            :key="feature.key"
            :feature="feature"
            :enabled="hasFeature(feature.key)"
            :license-tier="license.license_tier"
          />
        </div>
      </div>

      <!-- Security Features -->
      <div class="feature-category">
        <h3 class="feature-category-title">
          <i class="fas fa-shield-alt mr-2"></i>
          Security & Compliance
        </h3>
        <div class="feature-grid">
          <FeatureCard
            v-for="feature in securityFeatures"
            :key="feature.key"
            :feature="feature"
            :enabled="hasFeature(feature.key)"
            :license-tier="license.license_tier"
          />
        </div>
      </div>
    </div>

    <!-- Upgrade Prompt -->
    <div v-if="hasUpgradeOpportunities" class="upgrade-prompt mt-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <i class="fas fa-arrow-up text-blue-500 text-xl"></i>
        </div>
        <div class="ml-3 flex-1">
          <h4 class="font-medium text-blue-900">Unlock More Features</h4>
          <p class="mt-1 text-sm text-blue-700">
            Upgrade your license to access additional features and increase your limits.
          </p>
          <div class="mt-3">
            <button @click="$emit('upgrade-license')" class="btn btn-primary btn-sm">
              View Upgrade Options
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import FeatureCard from './FeatureCard.vue'

export default {
  name: 'FeatureToggles',
  components: {
    FeatureCard
  },
  props: {
    license: {
      type: Object,
      required: true
    }
  },
  emits: ['upgrade-license'],
  data() {
    return {
      coreFeatures: [
        {
          key: 'application_deployment',
          name: 'Application Deployment',
          description: 'Deploy applications from Git repositories',
          icon: 'fas fa-rocket',
          requiredTier: 'basic'
        },
        {
          key: 'database_management',
          name: 'Database Management',
          description: 'Create and manage databases',
          icon: 'fas fa-database',
          requiredTier: 'basic'
        },
        {
          key: 'ssl_certificates',
          name: 'SSL Certificates',
          description: 'Automatic SSL certificate provisioning',
          icon: 'fas fa-lock',
          requiredTier: 'basic'
        },
        {
          key: 'backup_restore',
          name: 'Backup & Restore',
          description: 'Automated backup and restore functionality',
          icon: 'fas fa-save',
          requiredTier: 'professional'
        }
      ],
      infrastructureFeatures: [
        {
          key: 'server_provisioning',
          name: 'Server Provisioning',
          description: 'Provision servers on cloud providers',
          icon: 'fas fa-server',
          requiredTier: 'professional'
        },
        {
          key: 'terraform_integration',
          name: 'Terraform Integration',
          description: 'Infrastructure as Code with Terraform',
          icon: 'fas fa-code-branch',
          requiredTier: 'professional'
        },
        {
          key: 'multi_cloud_support',
          name: 'Multi-Cloud Support',
          description: 'Deploy across multiple cloud providers',
          icon: 'fas fa-cloud',
          requiredTier: 'enterprise'
        },
        {
          key: 'load_balancing',
          name: 'Load Balancing',
          description: 'Advanced load balancing and scaling',
          icon: 'fas fa-balance-scale',
          requiredTier: 'enterprise'
        }
      ],
      enterpriseFeatures: [
        {
          key: 'white_label_branding',
          name: 'White-Label Branding',
          description: 'Customize platform appearance and branding',
          icon: 'fas fa-palette',
          requiredTier: 'professional'
        },
        {
          key: 'organization_hierarchy',
          name: 'Organization Hierarchy',
          description: 'Multi-tenant organization management',
          icon: 'fas fa-sitemap',
          requiredTier: 'professional'
        },
        {
          key: 'custom_domains',
          name: 'Custom Domains',
          description: 'Host platform on custom domains',
          icon: 'fas fa-globe',
          requiredTier: 'enterprise'
        },
        {
          key: 'advanced_rbac',
          name: 'Advanced RBAC',
          description: 'Role-based access control with custom permissions',
          icon: 'fas fa-users-cog',
          requiredTier: 'enterprise'
        }
      ],
      integrationFeatures: [
        {
          key: 'api_access',
          name: 'API Access',
          description: 'RESTful API for automation and integrations',
          icon: 'fas fa-plug',
          requiredTier: 'basic'
        },
        {
          key: 'webhook_support',
          name: 'Webhook Support',
          description: 'Event-driven webhooks and notifications',
          icon: 'fas fa-bell',
          requiredTier: 'professional'
        },
        {
          key: 'payment_processing',
          name: 'Payment Processing',
          description: 'Integrated payment and billing systems',
          icon: 'fas fa-credit-card',
          requiredTier: 'enterprise'
        },
        {
          key: 'domain_management',
          name: 'Domain Management',
          description: 'Purchase and manage domains through registrars',
          icon: 'fas fa-globe-americas',
          requiredTier: 'enterprise'
        }
      ],
      securityFeatures: [
        {
          key: 'mfa_authentication',
          name: 'Multi-Factor Authentication',
          description: 'Enhanced security with MFA support',
          icon: 'fas fa-mobile-alt',
          requiredTier: 'professional'
        },
        {
          key: 'audit_logging',
          name: 'Audit Logging',
          description: 'Comprehensive audit trails and compliance',
          icon: 'fas fa-clipboard-list',
          requiredTier: 'professional'
        },
        {
          key: 'ip_whitelisting',
          name: 'IP Whitelisting',
          description: 'Restrict access by IP address',
          icon: 'fas fa-shield-alt',
          requiredTier: 'enterprise'
        },
        {
          key: 'compliance_reporting',
          name: 'Compliance Reporting',
          description: 'GDPR, PCI-DSS, and SOC 2 compliance features',
          icon: 'fas fa-file-contract',
          requiredTier: 'enterprise'
        }
      ]
    }
  },
  computed: {
    licenseTierClass() {
      const tierClasses = {
        basic: 'bg-blue-100 text-blue-800',
        professional: 'bg-purple-100 text-purple-800',
        enterprise: 'bg-indigo-100 text-indigo-800'
      }
      return tierClasses[this.license.license_tier] || 'bg-gray-100 text-gray-800'
    },
    hasUpgradeOpportunities() {
      const allFeatures = [
        ...this.coreFeatures,
        ...this.infrastructureFeatures,
        ...this.enterpriseFeatures,
        ...this.integrationFeatures,
        ...this.securityFeatures
      ]
      
      return allFeatures.some(feature => 
        !this.hasFeature(feature.key) && 
        this.canUpgradeToFeature(feature.requiredTier)
      )
    }
  },
  methods: {
    hasFeature(featureKey) {
      return this.license.features && this.license.features.includes(featureKey)
    },
    
    canUpgradeToFeature(requiredTier) {
      const tierHierarchy = ['basic', 'professional', 'enterprise']
      const currentTierIndex = tierHierarchy.indexOf(this.license.license_tier)
      const requiredTierIndex = tierHierarchy.indexOf(requiredTier)
      
      return requiredTierIndex > currentTierIndex
    }
  }
}
</script>

<style scoped>
.feature-category {
  @apply border border-gray-200 dark:border-gray-700 rounded-lg p-4;
}

.feature-category-title {
  @apply text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center;
}

.feature-grid {
  @apply grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4;
}

.upgrade-prompt {
  @apply border-l-4 border-blue-500;
}

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
</style>