<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-content" @click.stop>
      <div class="flex justify-between items-center mb-4">
        <h3>Organization Hierarchy - {{ organization.name }}</h3>
        <button @click="$emit('close')" class="btn btn-sm">×</button>
      </div>

      <div class="mb-4">
        <button @click="refreshHierarchy" class="btn btn-sm">
          Refresh
        </button>
      </div>

      <!-- Hierarchy Tree -->
      <div class="hierarchy-tree">
        <HierarchyNode 
          v-if="hierarchyData"
          :node="hierarchyData"
          @switch-organization="switchToOrganization"
        />
        
        <div v-if="!hierarchyData" class="text-center py-8 opacity-75">
          Loading hierarchy...
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import HierarchyNode from './HierarchyNode.vue'

export default {
  name: 'OrganizationHierarchy',
  components: {
    HierarchyNode
  },
  props: {
    organization: {
      type: Object,
      required: true
    }
  },
  emits: ['close'],
  data() {
    return {
      hierarchyData: null
    }
  },
  async mounted() {
    await this.loadHierarchy()
  },
  methods: {
    async loadHierarchy() {
      try {
        const response = await fetch(`/internal-api/organizations/${this.organization.id}/hierarchy`)
        const data = await response.json()
        this.hierarchyData = data.hierarchy
      } catch (error) {
        console.error('Failed to load hierarchy:', error)
      }
    },

    async refreshHierarchy() {
      await this.loadHierarchy()
    },

    async switchToOrganization(organizationId) {
      try {
        const response = await fetch('/internal-api/organizations/switch', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ organization_id: organizationId })
        })

        if (response.ok) {
          window.location.href = '/dashboard'
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to switch organization')
        }
      } catch (error) {
        alert('Failed to switch organization')
      }
    }
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1001;
}

.modal-content {
  background: white;
  padding: 2rem;
  border-radius: 0.5rem;
  max-width: 800px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.dark .modal-content {
  background: #1f2937;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.875rem;
  background: #6b7280;
  color: white;
}

.hierarchy-tree {
  max-height: 60vh;
  overflow-y: auto;
}
</style>