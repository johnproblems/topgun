<template>
  <div class="organization-manager p-4 min-h-screen">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1>Organization Management</h1>
        <div class="subtitle">Manage your organization hierarchy and access control.</div>
      </div>
      
      <button 
        v-if="Object.keys(hierarchyTypes).length > 0"
        @click="showCreateModal = true"
        class="button"
      >
        Create Organization
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="mb-4 p-4 rounded bg-blue-100 border border-blue-400 text-blue-700">
      Loading organizations...
    </div>

    <!-- Success/Error Messages -->
    <div v-if="message.text" :class="messageClass" class="mb-4 p-4 rounded">
      {{ message.text }}
      <div v-if="message.type === 'error'" class="mt-2 text-sm">
        Check the browser console for more details.
      </div>
    </div>

    <!-- Current Organization Info -->
    <div v-if="currentOrganization" class="box mb-6">
      <h2>Current Organization</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
        <div>
          <span class="font-medium">Name:</span> {{ currentOrganization.name }}
        </div>
        <div>
          <span class="font-medium">Type:</span> 
          <span class="capitalize">{{ currentOrganization.hierarchy_type.replace('_', ' ') }}</span>
        </div>
        <div>
          <span class="font-medium">Level:</span> {{ currentOrganization.hierarchy_level }}
        </div>
      </div>
    </div>

    <!-- Organizations List -->
    <div class="box">
      <div class="flex justify-between items-center mb-4">
        <div>
          <h2>Accessible Organizations</h2>
          <div class="subtitle">Organizations you have access to manage or view.</div>
        </div>
      </div>
      
      <div class="space-y-4">
        <div 
          v-for="organization in organizations" 
          :key="organization.id"
          class="border dark:border-coolgray-200 rounded-lg p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <h4 class="font-medium">{{ organization.name }}</h4>
                <span 
                  v-if="organization.id === currentOrganization?.id"
                  class="badge bg-success"
                >
                  Current
                </span>
              </div>
              <div class="mt-1 flex items-center text-sm opacity-75 gap-2">
                <span class="capitalize">{{ organization.hierarchy_type.replace('_', ' ') }}</span>
                <span>•</span>
                <span>Level {{ organization.hierarchy_level }}</span>
                <span v-if="organization.parent">•</span>
                <span v-if="organization.parent">Parent: {{ organization.parent.name }}</span>
              </div>
            </div>
            
            <div class="flex gap-2">
              <button 
                v-if="organization.id !== currentOrganization?.id"
                @click="switchToOrganization(organization.id)"
                class="btn btn-sm"
              >
                Switch To
              </button>
              
              <button 
                @click="viewHierarchy(organization)"
                class="btn btn-sm"
              >
                Hierarchy
              </button>
              
              <button 
                @click="editOrganization(organization)"
                class="btn btn-sm"
              >
                Edit
              </button>
              
              <button 
                @click="manageUsers(organization)"
                class="btn btn-sm"
              >
                Users
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="organizations.length === 0" class="text-center py-8 opacity-75">
          No organizations found.
        </div>
      </div>
    </div>

    <!-- Create Organization Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content" @click.stop>
        <h3>Create New Organization</h3>
        <form @submit.prevent="createOrganization" class="space-y-4">
          <div>
            <label>Organization Name</label>
            <input 
              v-model="form.name" 
              type="text" 
              required 
              class="input"
              placeholder="Enter organization name"
            />
          </div>

          <div>
            <label>Hierarchy Type</label>
            <select v-model="form.hierarchy_type" required class="select">
              <option v-for="(label, value) in hierarchyTypes" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
          </div>

          <div v-if="availableParents.length > 0">
            <label>Parent Organization (Optional)</label>
            <select v-model="form.parent_organization_id" class="select">
              <option value="">Select Parent</option>
              <option v-for="parent in availableParents" :key="parent.id" :value="parent.id">
                {{ parent.name }}
              </option>
            </select>
          </div>

          <div>
            <label>
              <input v-model="form.is_active" type="checkbox" />
              Active
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <button type="button" @click="closeModals" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary">
              Create Organization
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Organization Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content" @click.stop>
        <h3>Edit Organization</h3>
        <form @submit.prevent="updateOrganization" class="space-y-4">
          <div>
            <label>Organization Name</label>
            <input 
              v-model="form.name" 
              type="text" 
              required 
              class="input"
              placeholder="Enter organization name"
            />
          </div>

          <div>
            <label>
              <input v-model="form.is_active" type="checkbox" />
              Active
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <button type="button" @click="closeModals" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary">
              Update Organization
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- User Management Modal -->
    <UserManagement 
      v-if="showUserModal"
      :organization="selectedOrganization"
      @close="showUserModal = false"
    />

    <!-- Hierarchy View Modal -->
    <OrganizationHierarchy 
      v-if="showHierarchyModal"
      :organization="selectedOrganization"
      @close="showHierarchyModal = false"
    />
  </div>
</template>

<script>
import UserManagement from './UserManagement.vue'
import OrganizationHierarchy from './OrganizationHierarchy.vue'

export default {
  name: 'OrganizationManager',
  components: {
    UserManagement,
    OrganizationHierarchy
  },
  data() {
    return {
      organizations: [],
      currentOrganization: null,
      hierarchyTypes: {},
      availableParents: [],
      showCreateModal: false,
      showEditModal: false,
      showUserModal: false,
      showHierarchyModal: false,
      selectedOrganization: null,
      message: { text: '', type: '' },
      form: {
        name: '',
        hierarchy_type: 'top_branch',
        parent_organization_id: null,
        is_active: true
      },
      loading: true
    }
  },
  computed: {
    messageClass() {
      return {
        'bg-green-100 border border-green-400 text-green-700': this.message.type === 'success',
        'bg-red-100 border border-red-400 text-red-700': this.message.type === 'error',
        'bg-blue-100 border border-blue-400 text-blue-700': this.message.type === 'info'
      }
    }
  },
  async mounted() {
    console.log('OrganizationManager mounted')
    await this.loadData()
    console.log('Data loaded:', { organizations: this.organizations, currentOrganization: this.currentOrganization })
  },
  methods: {
    async loadData() {
      try {
        this.loading = true
        console.log('Loading data from /internal-api/organizations')
        const response = await fetch('/internal-api/organizations', {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          }
        })
        console.log('Response status:', response.status)
        console.log('Response headers:', Object.fromEntries(response.headers.entries()))
        
        if (response.status === 401) {
          this.showMessage('Authentication required - please log in', 'error')
          console.error('401 Unauthorized - redirecting to login')
          window.location.href = '/login'
          return
        }
        
        if (response.status === 404) {
          this.showMessage('API endpoint not found - route configuration issue', 'error')
          console.error('404 Not Found - API route /api/organizations not configured properly')
          return
        }
        
        if (!response.ok) {
          const errorText = await response.text()
          console.error('HTTP Error:', response.status, response.statusText, errorText)
          this.showMessage(`HTTP Error ${response.status}: ${response.statusText}`, 'error')
          return
        }
        
        const data = await response.json()
        console.log('Response data:', data)
        
        this.organizations = data.organizations || []
        this.currentOrganization = data.currentOrganization || null
        this.hierarchyTypes = data.hierarchyTypes || {}
        this.availableParents = data.availableParents || []
        
        console.log('Data loaded successfully:', {
          organizationsCount: this.organizations.length,
          currentOrg: this.currentOrganization?.name || 'none',
          hierarchyTypesCount: Object.keys(this.hierarchyTypes).length
        })
        
      } catch (error) {
        console.error('Error loading data:', error)
        this.showMessage(`Failed to load organizations: ${error.message}`, 'error')
      } finally {
        this.loading = false
      }
    },

    async createOrganization() {
      try {
        const response = await fetch('/internal-api/organizations', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(this.form)
        })

        if (response.ok) {
          this.showMessage('Organization created successfully', 'success')
          this.closeModals()
          await this.loadData()
        } else {
          const error = await response.json()
          this.showMessage(error.message || 'Failed to create organization', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to create organization', 'error')
      }
    },

    async updateOrganization() {
      try {
        const response = await fetch(`/internal-api/organizations/${this.selectedOrganization.id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(this.form)
        })

        if (response.ok) {
          this.showMessage('Organization updated successfully', 'success')
          this.closeModals()
          await this.loadData()
        } else {
          const error = await response.json()
          this.showMessage(error.message || 'Failed to update organization', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to update organization', 'error')
      }
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
          this.showMessage(error.message || 'Failed to switch organization', 'error')
        }
      } catch (error) {
        this.showMessage('Failed to switch organization', 'error')
      }
    },

    editOrganization(organization) {
      this.selectedOrganization = organization
      this.form.name = organization.name
      this.form.hierarchy_type = organization.hierarchy_type
      this.form.parent_organization_id = organization.parent_organization_id
      this.form.is_active = organization.is_active
      this.showEditModal = true
    },

    manageUsers(organization) {
      this.selectedOrganization = organization
      this.showUserModal = true
    },

    viewHierarchy(organization) {
      this.selectedOrganization = organization
      this.showHierarchyModal = true
    },

    closeModals() {
      this.showCreateModal = false
      this.showEditModal = false
      this.showUserModal = false
      this.showHierarchyModal = false
      this.selectedOrganization = null
      this.resetForm()
    },

    resetForm() {
      this.form = {
        name: '',
        hierarchy_type: 'end_user',
        parent_organization_id: null,
        is_active: true
      }
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
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 2rem;
  border-radius: 0.5rem;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.dark .modal-content {
  background: #1f2937;
}

.input, .select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  margin-top: 0.25rem;
}

.dark .input, .dark .select {
  background: #374151;
  border-color: #4b5563;
  color: white;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.875rem;
}

label {
  display: block;
  font-weight: 500;
  margin-bottom: 0.25rem;
}
</style>