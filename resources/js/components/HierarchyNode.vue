<template>
  <div class="hierarchy-node">
    <div class="node-content">
      <div class="flex items-center gap-2">
        <button 
          v-if="node.children && node.children.length > 0"
          @click="toggleExpanded"
          class="toggle-btn"
        >
          {{ isExpanded ? '−' : '+' }}
        </button>
        <div v-else class="toggle-spacer"></div>
        
        <div class="node-info">
          <div class="flex items-center gap-2">
            <span class="font-medium">{{ node.name }}</span>
            <span class="badge">{{ node.hierarchy_type.replace('_', ' ') }}</span>
            <span class="text-sm opacity-75">Level {{ node.hierarchy_level }}</span>
          </div>
          
          <div class="text-sm opacity-75 mt-1">
            {{ node.users_count }} users
          </div>
        </div>
      </div>
      
      <div class="node-actions">
        <button 
          @click="$emit('switch-organization', node.id)"
          class="btn btn-sm"
        >
          Switch To
        </button>
      </div>
    </div>
    
    <!-- Children -->
    <div v-if="isExpanded && node.children && node.children.length > 0" class="children">
      <HierarchyNode 
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        @switch-organization="$emit('switch-organization', $event)"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'HierarchyNode',
  props: {
    node: {
      type: Object,
      required: true
    }
  },
  emits: ['switch-organization'],
  data() {
    return {
      isExpanded: true
    }
  },
  methods: {
    toggleExpanded() {
      this.isExpanded = !this.isExpanded
    }
  }
}
</script>

<style scoped>
.hierarchy-node {
  margin-bottom: 0.5rem;
}

.node-content {
  display: flex;
  align-items: center;
  justify-content: between;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  background: #f9fafb;
}

.dark .node-content {
  border-color: #4b5563;
  background: #374151;
}

.toggle-btn {
  width: 1.5rem;
  height: 1.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.dark .toggle-btn {
  background: #4b5563;
  border-color: #6b7280;
  color: white;
}

.toggle-spacer {
  width: 1.5rem;
}

.node-info {
  flex: 1;
  margin-left: 0.5rem;
}

.node-actions {
  margin-left: auto;
}

.children {
  margin-left: 2rem;
  margin-top: 0.5rem;
}

.badge {
  padding: 0.125rem 0.375rem;
  background: #e5e7eb;
  color: #374151;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  text-transform: capitalize;
}

.dark .badge {
  background: #4b5563;
  color: #d1d5db;
}

.btn {
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
  background: #3b82f6;
  color: white;
  font-size: 0.875rem;
}

.btn:hover {
  background: #2563eb;
}
</style>