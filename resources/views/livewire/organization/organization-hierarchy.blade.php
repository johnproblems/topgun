<div>
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

    @if($rootOrganization && !empty($hierarchyData))
        <div class="flex justify-between items-center mb-4">
            <h2>Organization Hierarchy</h2>
            <x-forms.button wire:click="refreshHierarchy" class="btn-sm">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </x-forms.button>
        </div>
        
        <div class="space-y-4">
            <!-- Root Organization -->
            <div class="border dark:border-coolgray-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button wire:click="toggleNode({{ json_encode($rootOrganization->id) }})" class="text-lg">
                            @if($this->isNodeExpanded($rootOrganization->id))
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            @endif
                        </button>
                        
                        <span class="text-lg">{{ $this->getHierarchyTypeIcon($hierarchyData['hierarchy_type']) }}</span>
                        
                        <div>
                            <h3 class="font-medium">{{ $hierarchyData['name'] }}</h3>
                            <div class="flex items-center gap-2 text-sm opacity-75">
                                <span class="badge {{ $this->getHierarchyTypeColor($hierarchyData['hierarchy_type']) }}">
                                    {{ str_replace('_', ' ', $hierarchyData['hierarchy_type']) }}
                                </span>
                                <span>Level {{ $hierarchyData['hierarchy_level'] }}</span>
                                <span>{{ $hierarchyData['user_count'] }} users</span>
                                @if($hierarchyData['is_active'])
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-error">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        @if($rootOrganization->id !== \App\Helpers\OrganizationContext::currentId())
                            <x-forms.button wire:click="switchToOrganization({{ json_encode($rootOrganization->id) }})" class="btn-sm">
                                Switch To
                            </x-forms.button>
                        @endif
                        
                        @if($this->canManageOrganization($rootOrganization->id))
                            <x-forms.button class="btn-sm" onclick="window.open('/organization/{{ $rootOrganization->id }}/edit', '_blank')">
                                Manage
                            </x-forms.button>
                        @endif
                    </div>
                </div>

                <!-- Usage Statistics -->
                @if($this->isNodeExpanded($rootOrganization->id))
                    <div class="mt-4 pl-7">
                        @php
                            $usage = $this->getOrganizationUsage($rootOrganization->id);
                        @endphp
                        @if(!empty($usage))
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div class="text-center p-2 bg-gray-50 dark:bg-coolgray-100 rounded">
                                    <div class="font-medium">{{ $usage['users'] ?? 0 }}</div>
                                    <div class="opacity-75">Users</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 dark:bg-coolgray-100 rounded">
                                    <div class="font-medium">{{ $usage['servers'] ?? 0 }}</div>
                                    <div class="opacity-75">Servers</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 dark:bg-coolgray-100 rounded">
                                    <div class="font-medium">{{ $usage['applications'] ?? 0 }}</div>
                                    <div class="opacity-75">Applications</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 dark:bg-coolgray-100 rounded">
                                    <div class="font-medium">{{ $usage['children'] ?? 0 }}</div>
                                    <div class="opacity-75">Children</div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Child Organizations -->
            @if($this->isNodeExpanded($rootOrganization->id) && !empty($hierarchyData['children']))
                <div class="ml-8 space-y-3">
                    @foreach($hierarchyData['children'] as $child)
                        @include('livewire.organization.partials.hierarchy-node', ['node' => $child, 'level' => 1])
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="text-center py-8">
            <div class="mb-4">
                <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <p class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No Organization Data</p>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Unable to load organization hierarchy. This might be due to connectivity issues.
            </p>
            <div class="space-x-2">
                <x-forms.button wire:click="refreshHierarchy" class="btn-sm">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Try Again
                </x-forms.button>
                <x-forms.button onclick="window.location.reload()" class="btn-sm bg-gray-500 hover:bg-gray-600">
                    Reload Page
                </x-forms.button>
            </div>
        </div>
    @endif
</div>