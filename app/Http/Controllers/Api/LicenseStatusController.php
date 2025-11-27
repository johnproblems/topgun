<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ResourceProvisioningService;
use App\Traits\LicenseValidation;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LicenseStatusController extends Controller
{
    use LicenseValidation;

    protected ResourceProvisioningService $provisioningService;

    public function __construct(ResourceProvisioningService $provisioningService)
    {
        $this->provisioningService = $provisioningService;
    }

    #[OA\Get(
        summary: 'License Status',
        description: 'Get current license status and available features.',
        path: '/license/status',
        operationId: 'get-license-status',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['License'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'License status retrieved successfully.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'license_info' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'license_tier' => ['type' => 'string'],
                                        'features' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        'limits' => ['type' => 'object'],
                                        'expires_at' => ['type' => 'string', 'nullable' => true],
                                        'is_trial' => ['type' => 'boolean'],
                                        'days_until_expiration' => ['type' => 'integer', 'nullable' => true],
                                    ],
                                ],
                                'resource_limits' => ['type' => 'object'],
                                'deployment_options' => ['type' => 'object'],
                                'provisioning_status' => ['type' => 'object'],
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 403,
                ref: '#/components/responses/403',
            ),
        ]
    )]
    public function status(Request $request)
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $licenseInfo = $this->getLicenseFeatures();
        $resourceLimits = $this->provisioningService->getResourceLimits($organization);
        $deploymentOptions = $this->provisioningService->getAvailableDeploymentOptions($organization);

        // Check provisioning status for each resource type
        $provisioningStatus = [
            'servers' => $this->provisioningService->canProvisionServer($organization),
            'applications' => $this->provisioningService->canDeployApplication($organization),
            'domains' => $this->provisioningService->canManageDomains($organization),
            'infrastructure' => $this->provisioningService->canProvisionInfrastructure($organization),
        ];

        return response()->json([
            'license_info' => $licenseInfo,
            'resource_limits' => $resourceLimits,
            'deployment_options' => $deploymentOptions,
            'provisioning_status' => $provisioningStatus,
        ]);
    }

    #[OA\Get(
        summary: 'Check Feature',
        description: 'Check if a specific feature is available in the current license.',
        path: '/license/features/{feature}',
        operationId: 'check-license-feature',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['License'],
        parameters: [
            new OA\Parameter(
                name: 'feature',
                in: 'path',
                required: true,
                description: 'Feature name to check',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Feature availability checked.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'feature' => ['type' => 'string'],
                                'available' => ['type' => 'boolean'],
                                'license_tier' => ['type' => 'string'],
                                'upgrade_required' => ['type' => 'boolean'],
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 403,
                ref: '#/components/responses/403',
            ),
        ]
    )]
    public function checkFeature(Request $request, string $feature)
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $license = $organization->activeLicense;
        $available = $license ? $license->hasFeature($feature) : false;

        return response()->json([
            'feature' => $feature,
            'available' => $available,
            'license_tier' => $license?->license_tier,
            'upgrade_required' => ! $available && $license !== null,
        ]);
    }

    #[OA\Get(
        summary: 'Check Deployment Option',
        description: 'Check if a specific deployment option is available in the current license.',
        path: '/license/deployment-options/{option}',
        operationId: 'check-deployment-option',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['License'],
        parameters: [
            new OA\Parameter(
                name: 'option',
                in: 'path',
                required: true,
                description: 'Deployment option to check',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Deployment option availability checked.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'option' => ['type' => 'string'],
                                'available' => ['type' => 'boolean'],
                                'license_tier' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 403,
                ref: '#/components/responses/403',
            ),
        ]
    )]
    public function checkDeploymentOption(Request $request, string $option)
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $deploymentOptions = $this->provisioningService->getAvailableDeploymentOptions($organization);
        $available = array_key_exists($option, $deploymentOptions['available_options']);
        $description = $deploymentOptions['available_options'][$option] ?? null;

        return response()->json([
            'option' => $option,
            'available' => $available,
            'license_tier' => $deploymentOptions['license_tier'],
            'description' => $description,
        ]);
    }

    #[OA\Get(
        summary: 'Resource Limits',
        description: 'Get current resource usage and limits.',
        path: '/license/limits',
        operationId: 'get-resource-limits',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['License'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resource limits retrieved successfully.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'has_license' => ['type' => 'boolean'],
                                'license_tier' => ['type' => 'string'],
                                'limits' => ['type' => 'object'],
                                'usage' => ['type' => 'object'],
                                'expires_at' => ['type' => 'string', 'nullable' => true],
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 403,
                ref: '#/components/responses/403',
            ),
        ]
    )]
    public function limits(Request $request)
    {
        $organization = $this->getCurrentOrganization();
        if (! $organization) {
            return response()->json(['error' => 'No organization context found'], 403);
        }

        $resourceLimits = $this->provisioningService->getResourceLimits($organization);

        return response()->json($resourceLimits);
    }
}
