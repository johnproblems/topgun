<div>
    <x-slot:title>
        Organization Management | Coolify
    </x-slot>
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1>Organization Management</h1>
            <div class="subtitle">Manage your organization hierarchy and access control.</div>
        </div>
        
        @if(count($hierarchyTypes) > 0)
            <x-forms.button wire:click="openCreateForm" onclick="createOrganizationModal.showModal()">
                Create Organization
            </x-forms.button>
        @endif
    </div>

    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Current Organization Info -->
    @if($currentOrganization)
        <div class="box mb-6">
            <h2>Current Organization</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <span class="font-medium">Name:</span> {{ $currentOrganization->name }}
                </div>
                <div>
                    <span class="font-medium">Type:</span> 
                    <span class="capitalize">{{ str_replace('_', ' ', $currentOrganization->hierarchy_type) }}</span>
                </div>
                <div>
                    <span class="font-medium">Level:</span> {{ $currentOrganization->hierarchy_level }}
                </div>
            </div>
        </div>
    @endif

    <!-- Organizations List -->
    <div class="box">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2>Accessible Organizations</h2>
                <div class="subtitle">Organizations you have access to manage or view.</div>
            </div>
        </div>
        
        <div class="space-y-4">
            @forelse($organizations as $organization)
                <div class="border dark:border-coolgray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium">
                                    {{ $organization->name }}
                                </h4>
                                @if($organization->id === $currentOrganization?->id)
                                    <span class="badge bg-success">Current</span>
                                @endif
                            </div>
                            <div class="mt-1 flex items-center text-sm opacity-75 gap-2">
                                <span class="capitalize">{{ str_replace('_', ' ', $organization->hierarchy_type) }}</span>
                                <span>•</span>
                                <span>Level {{ $organization->hierarchy_level }}</span>
                                @if($organization->parent)
                                    <span>•</span>
                                    <span>Parent: {{ $organization->parent->name }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            @if($organization->id !== $currentOrganization?->id)
                                <x-forms.button wire:click="switchToOrganization({{ json_encode($organization->id) }})" class="btn-sm">
                                    Switch To
                                </x-forms.button>
                            @endif
                            
                            <x-forms.button wire:click="viewHierarchy({{ json_encode($organization->id) }})" onclick="hierarchyModal.showModal()" class="btn-sm">
                                Hierarchy
                            </x-forms.button>
                            
                            <x-forms.button wire:click="editOrganization({{ json_encode($organization->id) }})" onclick="editOrganizationModal.showModal()" class="btn-sm">
                                Edit
                            </x-forms.button>
                            
                            <x-forms.button wire:click="manageUsers({{ json_encode($organization->id) }})" class="btn-sm">
                                Users
                            </x-forms.button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 opacity-75">
                    No organizations found.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Create Organization Modal -->
    @if($showCreateForm)
        <x-modal modalId="createOrganizationModal">
            <x-slot:modalTitle>Create New Organization</x-slot:modalTitle>
            <x-slot:modalBody>
                <form wire:submit.prevent="createOrganization" class="space-y-4">
                    <x-forms.input 
                        wire:model="name" 
                        label="Organization Name" 
                        placeholder="Enter organization name"
                        required />

                    <x-forms.select wire:model="hierarchy_type" label="Hierarchy Type" required>
                        @foreach($hierarchyTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-forms.select>

                    @if(count($availableParents) > 0)
                        <x-forms.select wire:model="parent_organization_id" label="Parent Organization (Optional)">
                            <option value="">Select Parent</option>
                            @foreach($availableParents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </x-forms.select>
                    @endif

                    <x-forms.checkbox wire:model="is_active" label="Active" />

                    <div class="flex justify-end gap-2 pt-4">
                        <x-forms.button type="button" onclick="createOrganizationModal.close()" wire:click="closeModals">
                            Cancel
                        </x-forms.button>
                        <x-forms.button type="submit">
                            Create Organization
                        </x-forms.button>
                    </div>
                </form>
            </x-slot:modalBody>
        </x-modal>
        
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.on('showCreateForm', () => {
                    createOrganizationModal.showModal();
                });
            });
        </script>
    @endif

    <!-- Edit Organization Modal -->
    @if($showEditForm && $selectedOrganization)
        <x-modal modalId="editOrganizationModal">
            <x-slot:modalTitle>Edit Organization</x-slot:modalTitle>
            <x-slot:modalBody>
                <form wire:submit.prevent="updateOrganization" class="space-y-4">
                    <x-forms.input 
                        wire:model="name" 
                        label="Organization Name" 
                        placeholder="Enter organization name"
                        required />

                    <x-forms.checkbox wire:model="is_active" label="Active" />

                    <div class="flex justify-end gap-2 pt-4">
                        <x-forms.button type="button" onclick="editOrganizationModal.close()" wire:click="closeModals">
                            Cancel
                        </x-forms.button>
                        <x-forms.button type="submit">
                            Update Organization
                        </x-forms.button>
                    </div>
                </form>
            </x-slot:modalBody>
        </x-modal>
    @endif

    <!-- User Management Slide Over -->
    @if($showUserManagement && $selectedOrganization)
        <x-slide-over fullScreen>
            <x-slot:title>Manage Users - {{ $selectedOrganization->name }}</x-slot:title>
            <x-slot:content>
                <livewire:organization.user-management :organization="$selectedOrganization" :key="'user-mgmt-'.$selectedOrganization->id" />
            </x-slot:content>
        </x-slide-over>
    @endif

    <!-- Hierarchy View Modal -->
    @if($showHierarchyView && $selectedOrganization)
        <x-modal modalId="hierarchyModal">
            <x-slot:modalTitle>Organization Hierarchy - {{ $selectedOrganization->name }}</x-slot:modalTitle>
            <x-slot:modalBody>
                <livewire:organization.organization-hierarchy :organization="$selectedOrganization" :key="'hierarchy-'.$selectedOrganization->id" />
            </x-slot:modalBody>
        </x-modal>
    @endif
</div>