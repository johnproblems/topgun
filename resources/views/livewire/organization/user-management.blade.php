<div class="space-y-6">
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

    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3>Organization Users</h3>
            <div class="subtitle">Manage users and their roles in {{ $organization->name }}</div>
        </div>
        <x-forms.button wire:click="openAddUserForm" onclick="addUserModal.showModal()">
            Add User
        </x-forms.button>
    </div>

    <!-- Search -->
    <div class="flex gap-4">
        <x-forms.input 
            wire:model.live.debounce.300ms="searchTerm" 
            placeholder="Search users by name or email..."
            class="flex-1" />
    </div>

    <!-- Users List -->
    <div class="space-y-3">
        @forelse($users as $user)
            <div class="border dark:border-coolgray-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-medium">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div>
                            <h4 class="font-medium">{{ $user->name }}</h4>
                            <p class="text-sm opacity-75">{{ $user->email }}</p>
                        </div>
                        <span class="badge {{ $this->getRoleColor($this->getUserRole($user)) }}">
                            {{ ucfirst($this->getUserRole($user)) }}
                        </span>
                        @if(!$user->pivot->is_active)
                            <span class="badge bg-error">Inactive</span>
                        @endif
                    </div>
                    
                    <div class="flex gap-2">
                        @if($this->canEditUser($user))
                            <x-forms.button wire:click="editUser({{ json_encode($user->id) }})" onclick="editUserModal.showModal()" class="btn-sm">
                                Edit
                            </x-forms.button>
                        @endif
                        
                        @if($this->canRemoveUser($user))
                            <x-forms.button 
                                wire:click="removeUser({{ json_encode($user->id) }})" 
                                class="btn-sm bg-red-500 hover:bg-red-600"
                                onclick="return confirm('Are you sure you want to remove this user?')">
                                Remove
                            </x-forms.button>
                        @endif
                    </div>
                </div>

                <!-- User Permissions -->
                @php
                    $permissions = $this->getUserPermissions($user);
                @endphp
                @if(!empty($permissions))
                    <div class="mt-3 pl-13">
                        <div class="text-sm opacity-75 mb-2">Custom Permissions:</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($permissions as $permission)
                                <span class="badge bg-gray-100 text-gray-800 text-xs">
                                    {{ $availablePermissions[$permission] ?? $permission }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-8 opacity-75">
                <p>No users found.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($users->hasPages())
        <div class="mt-6">
            {{ $users->links() }}
        </div>
    @endif

    <!-- Add User Modal -->
    @if($showAddUserForm)
        <x-modal modalId="addUserModal">
            <x-slot:modalTitle>Add User to Organization</x-slot:modalTitle>
            <x-slot:modalBody>
                <form wire:submit.prevent="addUser" class="space-y-4">
                    <div>
                        <x-forms.input 
                            wire:model.live.debounce.300ms="userEmail" 
                            label="User Email" 
                            placeholder="Enter user email address"
                            required />
                        
                        <!-- Available Users Dropdown -->
                        @if($availableUsers->count() > 0)
                            <div class="mt-2 border dark:border-coolgray-200 rounded-lg max-h-40 overflow-y-auto">
                                @foreach($availableUsers as $availableUser)
                                    <div wire:click="selectUser({{ json_encode($availableUser->id) }})" 
                                         class="p-2 hover:bg-gray-50 dark:hover:bg-coolgray-100 cursor-pointer border-b dark:border-coolgray-200 last:border-b-0">
                                        <div class="font-medium">{{ $availableUser->name }}</div>
                                        <div class="text-sm opacity-75">{{ $availableUser->email }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <x-forms.select wire:model="userRole" label="Role" required>
                        @foreach($availableRoles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-forms.select>

                    <div>
                        <label class="block text-sm font-medium mb-2">Custom Permissions (Optional)</label>
                        <div class="space-y-2 max-h-40 overflow-y-auto border dark:border-coolgray-200 rounded-lg p-3">
                            @foreach($availablePermissions as $value => $label)
                                <x-forms.checkbox wire:model="userPermissions" value="{{ $value }}" label="{{ $label }}" />
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <x-forms.button type="button" onclick="addUserModal.close()" wire:click="closeModals">
                            Cancel
                        </x-forms.button>
                        <x-forms.button type="submit">
                            Add User
                        </x-forms.button>
                    </div>
                </form>
            </x-slot:modalBody>
        </x-modal>
    @endif

    <!-- Edit User Modal -->
    @if($showEditUserForm && $selectedUser)
        <x-modal modalId="editUserModal">
            <x-slot:modalTitle>Edit User - {{ $selectedUser->name }}</x-slot:modalTitle>
            <x-slot:modalBody>
                <form wire:submit.prevent="updateUser" class="space-y-4">
                    <div class="p-3 bg-gray-50 dark:bg-coolgray-100 rounded-lg">
                        <div class="font-medium">{{ $selectedUser->name }}</div>
                        <div class="text-sm opacity-75">{{ $selectedUser->email }}</div>
                    </div>

                    <x-forms.select wire:model="userRole" label="Role" required>
                        @foreach($availableRoles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-forms.select>

                    <div>
                        <label class="block text-sm font-medium mb-2">Custom Permissions (Optional)</label>
                        <div class="space-y-2 max-h-40 overflow-y-auto border dark:border-coolgray-200 rounded-lg p-3">
                            @foreach($availablePermissions as $value => $label)
                                <x-forms.checkbox wire:model="userPermissions" value="{{ $value }}" label="{{ $label }}" />
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <x-forms.button type="button" onclick="editUserModal.close()" wire:click="closeModals">
                            Cancel
                        </x-forms.button>
                        <x-forms.button type="submit">
                            Update User
                        </x-forms.button>
                    </div>
                </form>
            </x-slot:modalBody>
        </x-modal>
    @endif
</div>