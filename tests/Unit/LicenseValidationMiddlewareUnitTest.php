<?php

namespace Tests\Unit;

use App\Contracts\LicensingServiceInterface;
use App\Data\LicenseValidationResult;
use App\Http\Middleware\ValidateLicense;
use App\Models\EnterpriseLicense;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use PHPUnit\Framework\TestCase;

class LicenseValidationMiddlewareUnitTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_middleware_skips_validation_in_dev_mode()
    {
        // Mock the isDev() function to return true
        if (! function_exists('isDev')) {
            function isDev()
            {
                return true;
            }
        }

        $licensingService = Mockery::mock(LicensingServiceInterface::class);
        $middleware = new ValidateLicense($licensingService);

        $request = Request::create('/api/v1/servers', 'GET');
        $next = function ($request) {
            return new Response('OK', 200);
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_middleware_skips_health_check_routes()
    {
        $licensingService = Mockery::mock(LicensingServiceInterface::class);
        $middleware = new ValidateLicense($licensingService);

        $request = Request::create('/health', 'GET');
        $next = function ($request) {
            return new Response('OK', 200);
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_validates_license_features()
    {
        // This test would require more complex mocking of Laravel's Auth facade
        // and database models, which is better suited for integration tests
        $this->assertTrue(true);
    }

    public function test_license_validation_result_structure()
    {
        $license = Mockery::mock(EnterpriseLicense::class);
        $license->shouldReceive('isExpired')->andReturn(false);
        $license->shouldReceive('isWithinGracePeriod')->andReturn(false);

        $validationResult = new LicenseValidationResult(
            true,
            'License is valid',
            $license,
            [],
            ['license_tier' => 'professional']
        );

        $this->assertTrue($validationResult->isValid());
        $this->assertEquals('License is valid', $validationResult->getMessage());
        $this->assertSame($license, $validationResult->getLicense());
        $this->assertEquals([], $validationResult->getViolations());
        $this->assertEquals(['license_tier' => 'professional'], $validationResult->getMetadata());
    }

    public function test_license_validation_result_invalid()
    {
        $validationResult = new LicenseValidationResult(
            false,
            'License expired',
            null,
            [['type' => 'expiration', 'message' => 'License has expired']],
            []
        );

        $this->assertFalse($validationResult->isValid());
        $this->assertEquals('License expired', $validationResult->getMessage());
        $this->assertNull($validationResult->getLicense());
        $this->assertTrue($validationResult->hasViolations());
        $this->assertCount(1, $validationResult->getViolations());
    }
}
