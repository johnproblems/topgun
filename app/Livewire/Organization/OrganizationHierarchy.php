<?php

namespace App\Livewire\Organization;

use App\Contracts\OrganizationServiceInterface;
use App\Helpers\OrganizationContext;
use App\Models\Organization;
use Livewire\Component;

class OrganizationHierarchy extends Component
{
    public $rootOrganization = null;

    public $hierarchyData = [];

    public $expandedNodes = [];

    public function mount(?Organization $organization = null)
    {
        // If no organization provided, use current organization
        $this->rootOrganization = $organization ?? OrganizationContext::current();

        if ($this->rootOrganization) {
            $this->loadHierarchy();
        }
    }

    public function render()
    {
        return view('livewire.organization.organization-hierarchy');
    }

    public function loadHierarchy()
    {
        if (! $this->rootOrganization) {
            return;
        }

        // Check permissions
        if (! OrganizationContext::can('view_organization', $this->rootOrganization)) {
            session()->flash('error', 'You do not have permission to view this organization hierarchy.');

            return;
        }

        try {
            $organizationService = app(OrganizationServiceInterface::class);
            $this->hierarchyData = $organizationService->getOrganizationHierarchy($this->rootOrganization);

            // Expand the root node by default
            $this->expandedNodes[$this->rootOrganization->id] = true;

        } catch (\Exception $e) {
            \Log::error('Failed to load organization hierarchy', [
                'organization_id' => $this->rootOrganization->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Provide fallback data structure
            $this->hierarchyData = [
                'id' => $this->rootOrganization->id,
                'name' => $this->rootOrganization->name,
                'hierarchy_type' => $this->rootOrganization->hierarchy_type,
                'hierarchy_level' => $this->rootOrganization->hierarchy_level,
                'is_active' => $this->rootOrganization->is_active,
                'user_count' => $this->rootOrganization->users()->count(),
                'children' => [],
            ];

            session()->flash('error', 'Failed to load complete organization hierarchy. Showing basic information only.');
        }
    }

    public function toggleNode($organizationId)
    {
        try {
            // Validate organization ID
            if (! is_numeric($organizationId) && ! is_string($organizationId)) {
                throw new \InvalidArgumentException('Invalid organization ID format');
            }

            if (isset($this->expandedNodes[$organizationId])) {
                unset($this->expandedNodes[$organizationId]);
            } else {
                $this->expandedNodes[$organizationId] = true;
            }
        } catch (\Exception $e) {
            \Log::error('Failed to toggle organization node', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to toggle organization view.');
        }
    }

    public function switchToOrganization($organizationId)
    {
        try {
            // Validate organization ID
            if (! is_numeric($organizationId) && ! is_string($organizationId)) {
                throw new \InvalidArgumentException('Invalid organization ID format');
            }

            $organization = Organization::findOrFail($organizationId);

            if (! OrganizationContext::can('switch_organization', $organization)) {
                session()->flash('error', 'You do not have permission to switch to this organization.');

                return;
            }

            $organizationService = app(OrganizationServiceInterface::class);
            $organizationService->switchUserOrganization(auth()->user(), $organization);

            session()->flash('success', 'Switched to '.$organization->name);

            return redirect()->to('/dashboard');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Organization not found for switch', [
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
            ]);
            session()->flash('error', 'Organization not found.');
        } catch (\Exception $e) {
            \Log::error('Failed to switch organization', [
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to switch organization. Please try again.');
        }
    }

    public function getOrganizationUsage($organizationId)
    {
        try {
            $organization = Organization::findOrFail($organizationId);
            $organizationService = app(OrganizationServiceInterface::class);

            return $organizationService->getOrganizationUsage($organization);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function isNodeExpanded($organizationId)
    {
        return isset($this->expandedNodes[$organizationId]);
    }

    public function canManageOrganization($organizationId)
    {
        try {
            $organization = Organization::findOrFail($organizationId);

            return OrganizationContext::can('manage_organization', $organization);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getHierarchyTypeIcon($hierarchyType)
    {
        return match ($hierarchyType) {
            'top_branch' => '🏢',
            'master_branch' => '🏬',
            'sub_user' => '👥',
            'end_user' => '👤',
            default => '📁'
        };
    }

    public function getHierarchyTypeColor($hierarchyType)
    {
        return match ($hierarchyType) {
            'top_branch' => 'bg-purple-100 text-purple-800',
            'master_branch' => 'bg-blue-100 text-blue-800',
            'sub_user' => 'bg-green-100 text-green-800',
            'end_user' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function refreshHierarchy()
    {
        $this->loadHierarchy();
        session()->flash('success', 'Organization hierarchy refreshed.');
    }
}
