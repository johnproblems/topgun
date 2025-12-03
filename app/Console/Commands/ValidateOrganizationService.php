<?php

namespace App\Console\Commands;

use App\Contracts\OrganizationServiceInterface;
use App\Services\OrganizationService;
use Illuminate\Console\Command;
use ReflectionClass;

class ValidateOrganizationService extends Command
{
    protected $signature = 'validate:organization-service';

    protected $description = 'Validate OrganizationService implementation';

    public function handle()
    {
        $this->info('🔍 Validating OrganizationService implementation...');

        // 1. Check if service is properly bound
        try {
            $service = app(OrganizationServiceInterface::class);
            $this->line('✅ Service binding works: '.get_class($service));
        } catch (\Exception $e) {
            $this->error('❌ Service binding failed: '.$e->getMessage());

            return 1;
        }

        // 2. Check if service implements interface
        if ($service instanceof OrganizationServiceInterface) {
            $this->line('✅ Service implements OrganizationServiceInterface');
        } else {
            $this->error('❌ Service does not implement OrganizationServiceInterface');

            return 1;
        }

        // 3. Check if all interface methods are implemented
        $interface = new ReflectionClass(OrganizationServiceInterface::class);
        $implementation = new ReflectionClass(OrganizationService::class);

        $interfaceMethods = $interface->getMethods();
        $implementationMethods = $implementation->getMethods();

        $implementedMethods = array_map(fn ($method) => $method->getName(), $implementationMethods);

        $this->info('📋 Checking interface method implementation...');

        foreach ($interfaceMethods as $method) {
            $methodName = $method->getName();
            if (in_array($methodName, $implementedMethods)) {
                $this->line("  ✅ {$methodName}");
            } else {
                $this->error("  ❌ {$methodName} - NOT IMPLEMENTED");
            }
        }

        // 4. Check protected methods exist
        $this->info('🔧 Checking protected helper methods...');

        $protectedMethods = [
            'validateOrganizationData',
            'validateHierarchyCreation',
            'validateRole',
            'checkRolePermission',
            'wouldCreateCircularDependency',
            'buildHierarchyTree',
        ];

        foreach ($protectedMethods as $methodName) {
            if ($implementation->hasMethod($methodName)) {
                $method = $implementation->getMethod($methodName);
                if ($method->isProtected()) {
                    $this->line("  ✅ {$methodName} (protected)");
                } else {
                    $this->line("  ⚠️  {$methodName} (not protected)");
                }
            } else {
                $this->error("  ❌ {$methodName} - NOT FOUND");
            }
        }

        // 5. Check if models exist and have required relationships
        $this->info('🏗️ Checking model relationships...');

        try {
            $organizationClass = new ReflectionClass(\App\Models\Organization::class);
            $userClass = new ReflectionClass(\App\Models\User::class);

            // Check Organization model methods
            $orgMethods = ['users', 'parent', 'children', 'activeLicense', 'canUserPerformAction'];
            foreach ($orgMethods as $method) {
                if ($organizationClass->hasMethod($method)) {
                    $this->line("  ✅ Organization::{$method}");
                } else {
                    $this->error("  ❌ Organization::{$method} - NOT FOUND");
                }
            }

            // Check User model methods
            $userMethods = ['organizations', 'currentOrganization', 'canPerformAction'];
            foreach ($userMethods as $method) {
                if ($userClass->hasMethod($method)) {
                    $this->line("  ✅ User::{$method}");
                } else {
                    $this->error("  ❌ User::{$method} - NOT FOUND");
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Model validation failed: '.$e->getMessage());
        }

        // 6. Check if helper classes exist
        $this->info('🛠️ Checking helper classes...');

        $helperClasses = [
            \App\Helpers\OrganizationContext::class,
            \App\Http\Middleware\EnsureOrganizationContext::class,
        ];

        foreach ($helperClasses as $class) {
            if (class_exists($class)) {
                $this->line('  ✅ '.class_basename($class));
            } else {
                $this->error('  ❌ '.class_basename($class).' - NOT FOUND');
            }
        }

        // 7. Check if Livewire component exists
        $this->info('🎨 Checking Livewire components...');

        if (class_exists(\App\Livewire\Organization\OrganizationManager::class)) {
            $this->line('  ✅ OrganizationManager component');
        } else {
            $this->error('  ❌ OrganizationManager component - NOT FOUND');
        }

        // 8. Validate hierarchy rules
        $this->info('📏 Validating hierarchy rules...');

        $service = new OrganizationService;
        $reflection = new ReflectionClass($service);

        try {
            $validateMethod = $reflection->getMethod('validateHierarchyCreation');
            $validateMethod->setAccessible(true);

            // Test valid hierarchy
            $mockParent = $this->createMockOrganization('top_branch');
            $validateMethod->invoke($service, $mockParent, 'master_branch');
            $this->line('  ✅ Valid hierarchy: top_branch -> master_branch');

            // Test invalid hierarchy
            try {
                $mockInvalidParent = $this->createMockOrganization('end_user');
                $validateMethod->invoke($service, $mockInvalidParent, 'master_branch');
                $this->error('  ❌ Invalid hierarchy validation failed');
            } catch (\InvalidArgumentException $e) {
                $this->line('  ✅ Invalid hierarchy properly rejected: '.$e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Hierarchy validation test failed: '.$e->getMessage());
        }

        $this->info('🎉 OrganizationService validation completed!');

        return 0;
    }

    private function createMockOrganization(string $hierarchyType)
    {
        $mock = $this->getMockBuilder(\App\Models\Organization::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->hierarchy_type = $hierarchyType;

        return $mock;
    }
}
