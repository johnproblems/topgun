<?php

namespace App\Http\Middleware;

use App\Contracts\OrganizationServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    public function __construct(
        protected OrganizationServiceInterface $organizationService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // If user doesn't have a current organization, set one
        if (! $user->current_organization_id) {
            $organizations = $this->organizationService->getUserOrganizations($user);

            if ($organizations->isNotEmpty()) {
                $firstOrg = $organizations->first();
                $this->organizationService->switchUserOrganization($user, $firstOrg);
                $user->refresh();
            }
        }

        // Verify user still has access to their current organization
        if ($user->current_organization_id) {
            $currentOrg = $user->currentOrganization;

            if (! $currentOrg || ! $this->organizationService->canUserPerformAction($user, $currentOrg, 'view_organization')) {
                // User lost access, switch to another organization or clear context
                $organizations = $this->organizationService->getUserOrganizations($user);

                if ($organizations->isNotEmpty()) {
                    $firstOrg = $organizations->first();
                    $this->organizationService->switchUserOrganization($user, $firstOrg);
                } else {
                    $user->update(['current_organization_id' => null]);
                }
            }
        }

        return $next($request);
    }
}
