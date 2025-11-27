<?php

namespace App\Livewire\Organization;

use App\Contracts\OrganizationServiceInterface;
use App\Helpers\OrganizationContext;
use App\Models\Organization;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    public $selectedOrganizationId = '';

    public $userOrganizations = [];

    public $currentOrganization = null;

    public function mount()
    {
        $this->currentOrganization = OrganizationContext::current();
        $this->selectedOrganizationId = $this->currentOrganization?->id ?? '';
        $this->loadUserOrganizations();
    }

    public function render()
    {
        return view('livewire.organization.organization-switcher');
    }

    public function loadUserOrganizations()
    {
        try {
            $this->userOrganizations = OrganizationContext::getUserOrganizations();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to load organizations: '.$e->getMessage());
            $this->userOrganizations = collect();
        }
    }

    public function updatedSelectedOrganizationId()
    {
        if ($this->selectedOrganizationId && $this->selectedOrganizationId !== 'default') {
            $this->switchToOrganization($this->selectedOrganizationId);
        }
    }

    public function switchToOrganization($organizationId)
    {
        if (! $organizationId || $organizationId === 'default') {
            return;
        }

        try {
            $organization = Organization::findOrFail($organizationId);

            // Check if user has access to this organization
            if (! $this->userOrganizations->contains('id', $organizationId)) {
                session()->flash('error', 'You do not have access to this organization.');

                return;
            }

            $organizationService = app(OrganizationServiceInterface::class);
            $organizationService->switchUserOrganization(auth()->user(), $organization);

            session()->flash('success', 'Switched to '.$organization->name);

            // Refresh the page to update the context
            return redirect()->to(request()->url());

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to switch organization: '.$e->getMessage());

            // Reset to current organization
            $this->selectedOrganizationId = $this->currentOrganization?->id ?? '';
        }
    }

    public function getOrganizationDisplayName($organization)
    {
        $hierarchyIcon = match ($organization->hierarchy_type) {
            'top_branch' => '🏢',
            'master_branch' => '🏬',
            'sub_user' => '👥',
            'end_user' => '👤',
            default => '📁'
        };

        return $hierarchyIcon.' '.$organization->name;
    }

    public function hasMultipleOrganizations()
    {
        return $this->userOrganizations->count() > 1;
    }
}
