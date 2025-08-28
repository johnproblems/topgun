<div>
    @if($this->hasMultipleOrganizations())
        <x-forms.select wire:model.live="selectedOrganizationId" label="Current Organization">
            <option value="default" disabled>Switch Organization</option>
            @foreach ($userOrganizations as $organization)
                <option value="{{ $organization->id }}" @if($organization->id === $currentOrganization?->id) selected @endif>
                    {{ $this->getOrganizationDisplayName($organization) }}
                </option>
            @endforeach
        </x-forms.select>
    @elseif($currentOrganization)
        <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-coolgray-100 rounded-lg">
            <span class="text-lg">{{ $this->getOrganizationDisplayName($currentOrganization) }}</span>
            <span class="badge {{ match($currentOrganization->hierarchy_type) {
                'top_branch' => 'bg-purple-100 text-purple-800',
                'master_branch' => 'bg-blue-100 text-blue-800',
                'sub_user' => 'bg-green-100 text-green-800',
                'end_user' => 'bg-gray-100 text-gray-800',
                default => 'bg-gray-100 text-gray-800'
            } }}">
                {{ str_replace('_', ' ', $currentOrganization->hierarchy_type) }}
            </span>
        </div>
    @else
        <div class="text-center py-4 opacity-75">
            <p>No organization selected</p>
        </div>
    @endif

    @if (session()->has('success'))
        <div class="mt-2 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mt-2 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif
</div>