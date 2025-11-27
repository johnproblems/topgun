<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-content" @click.stop>
      <div class="flex justify-between items-center mb-4">
        <h3>Manage Users - {{ organization.name }}</h3>
        <button @click="$emit('close')" class="btn btn-sm">×</button>
      </div>

      <!-- Add User Section -->
      <div class="mb-6">
        <button @click="showAddForm = true" class="btn btn-primary">
          Add User
        </button>
      </div>

      <!-- Search -->
      <div class="mb-4">
        <input 
          v-model="searchTerm"
          type="text"
          placeholder="Search users..."
          class="input"
        />
      </div>

      <!-- Users List -->
      <div class="space-y-4 max-h-96 overflow-y-auto">
        <div 
          v-for="user in filteredUsers" 
          :key="user.id"
          class="border dark:border-coolgray-200 rounded-lg p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="font-medium">{{ user.name }}</span>
                <span class="text-sm opacity-75">{{ user.email }}</span>
                <span :class="getRoleColor(user.role)" class="badge">
                  {{ user.role }}
                </span>
              </div>
              <div v-if="user.permissions && user.permissions.length > 0" class="mt-2">
                <div class="text-sm opacity-75 mb-1">Permissions:</div>
                <div class="flex flex-wrap gap-1">
                  <span 
                    v-for="permission in user.permissions" 
                    :key="permission"
                    class="badge bg-gray-100 text-gray-800 text-xs"
                  >
                    {{ availablePermissions[permission] || permission }}
                  </span>
                </div>
              </div>
            </div>
            
            <div class="flex gap-2">
              <button 
                @click="editUser(user)"
                class="btn btn-sm"
              >
                Edit
              </button>
              
              <button 
                @click="removeUser(user)"
                class="btn btn-sm btn-danger"
                :disabled="!canRemoveUser(user)"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="filteredUsers.length === 0" class="text-center py-8 opacity-75">
          No users found.
        </div>
      </div>

      <!-- Add User Modal -->
      <div v-if="showAddForm" class="modal-overlay" @click="closeAddForm">
        <div class="modal-content" @click.stop>
          <h4>Add User to Organization</h4>
          <form @submit.prevent="addUser" class="space-y-4">
            <div>
              <label>User Email</label>
              <input 
                v-model="userForm.email"
                type="email"
                required
                class="input"
                placeholder="Enter user email address"
                @input="searchUsers"
              />
              
              <!-- Available Users Dropdown -->
              <div v-if="availableUsers.length > 0" class="mt-2 border dark:border-coolgray-200 rounded-lg max-h-40 overflow-y-auto">
                <div 
                  v-for="availableUser in availableUsers" 
                  :key="availableUser.id"
                  @click="selectUser(availableUser)"
                  class="p-2 hover:bg-gray-100 dark:hover:bg-coolgray-200 cursor-pointer"
                >
                  <div class="font-medium">{{ availableUser.name }}</div>
                  <div class="text-sm opacity-75">{{ availableUser.email }}</div>
                </div>
              </div>
            </div>

            <div>
              <label>Role</label>
              <select v-model="userForm.role" required class="select">
                <option v-for="(label, value) in availableRoles" :key="value" :value="value">
                  {{ label }}
                </option>
              </select>
            </div>

            <div>
              <label>Custom Permissions (Optional)</label>
              <div class="space-y-2 max-h-40 overflow-y-auto border dark:border-coolgray-200 rounded-lg p-3">
                <label v-for="(label, value) in availablePermissions" :key="value" class="flex items-center">
                  <input 
                    v-model="userForm.permissions"
                    :value="value"
                    type="checkbox"
                    class="mr-2"
                  />
                  {{ label }}
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
              <button type="button" @click="closeAddForm" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary">
                Add User
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Edit User Modal -->
      <div v-if="showEditForm" class="modal-overlay" @click="closeEditForm">
        <div class="modal-content" @click.stop>
          <h4>Edit User</h4>
          <form @submit.prevent="updateUser" class="space-y-4">
            <div>
              <label>Role</label>
              <select v-model="userForm.role" required class="select">
                <option v-for="(label, value) in availableRoles" :key="value" :value="value">
                  {{ label }}
                </option>
              </select>
            </div>

            <div>
              <label>Custom Permissions (Optional)</label>
              <div class="space-y-2 max-h-40 overflow-y-auto border dark:border-coolgray-200 rounded-lg p-3">
                <label v-for="(label, value) in availablePermissions" :key="value" class="flex items-center">
                  <input 
                    v-model="userForm.permissions"
                    :value="value"
                    type="checkbox"
                    class="mr-2"
                  />
                  {{ label }}
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
              <button type="button" @click="closeEditForm" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary">
                Update User
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UserManagement',
  props: {
    organization: {
      type: Object,
      required: true
    }
  },
  emits: ['close'],
  data() {
    return {
      users: [],
      availableUsers: [],
      availableRoles: {},
      availablePermissions: {},
      searchTerm: '',
      showAddForm: false,
      showEditForm: false,
      selectedUser: null,
      userForm: {
        email: '',
        role: 'member',
        permissions: []
      }
    }
  },
  computed: {
    filteredUsers() {
      if (!this.searchTerm) return this.users
      
      return this.users.filter(user => 
        user.name.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
        user.email.toLowerCase().includes(this.searchTerm.toLowerCase())
      )
    }
  },
  async mounted() {
    await this.loadUsers()
    await this.loadRolesAndPermissions()
  },
  methods: {
    async loadUsers() {
      try {
        const response = await fetch(`/internal-api/organizations/${this.organization.id}/users`)
        const data = await response.json()
        this.users = data.users
      } catch (error) {
        console.error('Failed to load users:', error)
      }
    },

    async loadRolesAndPermissions() {
      try {
        const response = await fetch('/internal-api/organizations/roles-permissions')
        const data = await response.json()
        this.availableRoles = data.roles
        this.availablePermissions = data.permissions
      } catch (error) {
        console.error('Failed to load roles and permissions:', error)
      }
    },

    async searchUsers() {
      if (this.userForm.email.length < 3) {
        this.availableUsers = []
        return
      }

      try {
        const response = await fetch(`/internal-api/users/search?email=${encodeURIComponent(this.userForm.email)}&exclude_organization=${this.organization.id}`)
        const data = await response.json()
        this.availableUsers = data.users
      } catch (error) {
        console.error('Failed to search users:', error)
      }
    },

    selectUser(user) {
      this.userForm.email = user.email
      this.availableUsers = []
    },

    async addUser() {
      try {
        const response = await fetch(`/internal-api/organizations/${this.organization.id}/users`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(this.userForm)
        })

        if (response.ok) {
          this.closeAddForm()
          await this.loadUsers()
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to add user')
        }
      } catch (error) {
        alert('Failed to add user')
      }
    },

    editUser(user) {
      this.selectedUser = user
      this.userForm.role = user.role
      this.userForm.permissions = user.permissions || []
      this.showEditForm = true
    },

    async updateUser() {
      try {
        const response = await fetch(`/internal-api/organizations/${this.organization.id}/users/${this.selectedUser.id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({
            role: this.userForm.role,
            permissions: this.userForm.permissions
          })
        })

        if (response.ok) {
          this.closeEditForm()
          await this.loadUsers()
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to update user')
        }
      } catch (error) {
        alert('Failed to update user')
      }
    },

    async removeUser(user) {
      if (!confirm(`Are you sure you want to remove ${user.name} from this organization?`)) {
        return
      }

      try {
        const response = await fetch(`/internal-api/organizations/${this.organization.id}/users/${user.id}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })

        if (response.ok) {
          await this.loadUsers()
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to remove user')
        }
      } catch (error) {
        alert('Failed to remove user')
      }
    },

    canRemoveUser(user) {
      // Add logic to prevent removing last owner, etc.
      return true
    },

    getRoleColor(role) {
      const colors = {
        owner: 'bg-red-100 text-red-800',
        admin: 'bg-blue-100 text-blue-800',
        member: 'bg-green-100 text-green-800',
        viewer: 'bg-gray-100 text-gray-800'
      }
      return colors[role] || colors.viewer
    },

    closeAddForm() {
      this.showAddForm = false
      this.userForm = {
        email: '',
        role: 'member',
        permissions: []
      }
      this.availableUsers = []
    },

    closeEditForm() {
      this.showEditForm = false
      this.selectedUser = null
      this.userForm = {
        email: '',
        role: 'member',
        permissions: []
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

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.875rem;
}

.badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

label {
  display: block;
  font-weight: 500;
  margin-bottom: 0.25rem;
}
</style>