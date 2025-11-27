<div class="border dark:border-coolgray-200 rounded-lg p-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @if(!empty($node['children']))
                <button wire:click="toggleNode({{ json_encode($node['id']) }})" class="text-lg">
                    @if($this->isNodeExpanded($node['id']))
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    @endif
                </button>
            @else
                <div class="w-4 h-4"></div>
            @endif
            
            <span class="text-lg">{{ $this->getHierarchyTypeIcon($node['hierarchy_type']) }}</span>
            
            <div>
                <h4 class="font-medium">{{ $node['name'] }}</h4>
                <div class="flex items-center gap-2 text-sm opacity-75">
                    <span class="badge {{ $this->getHierarchyTypeColor($node['hierarchy_type']) }}">
                        {{ str_replace('_', ' ', $node['hierarchy_type']) }}
                    </span>
                    <span>Level {{ $node['hierarchy_level'] }}</span>
                    <span>{{ $node['user_count'] }} users</span>
                    @if($node['is_active'])
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-error">Inactive</span>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="flex gap-2">
            @if($node['id'] !== \App\Helpers\OrganizationContext::currentId())
                <x-forms.button wire:click="switchToOrganization({{ json_encode($node['id']) }})" class="btn-sm">
                    Switch To
                </x-forms.button>
            @endif
            
            @if($this->canManageOrganization($node['id']))
                <x-forms.button class="btn-sm" onclick="window.open('/organization/{{ $node['id'] }}/edit', '_blank')">
                    Manage
                </x-forms.button>
            @endif
        </div>
    </div>

    <!-- Usage Statistics -->
    @if($this->isNodeExpanded($node['id']))
        <div class="mt-3 pl-7">
            @php
                $usage = $this->getOrganizationUsage($node['id']);
            @endphp
            @if(!empty($usage))
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
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
@if($this->isNodeExpanded($node['id']) && !empty($node['children']))
    <div class="ml-6 mt-3 space-y-2">
        @foreach($node['children'] as $child)
            @include('livewire.organization.partials.hierarchy-node', ['node' => $child, 'level' => ($level ?? 0) + 1])
        @endforeach
    </div>
@endif