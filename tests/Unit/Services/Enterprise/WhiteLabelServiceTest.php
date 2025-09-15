<?php

namespace Tests\Unit\Services\Enterprise;

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use App\Services\Enterprise\BrandingCacheService;
use App\Services\Enterprise\DomainValidationService;
use App\Services\Enterprise\EmailTemplateService;
use App\Services\Enterprise\WhiteLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhiteLabelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WhiteLabelService $service;
    protected Organization $organization;
    protected WhiteLabelConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = new WhiteLabelService(
            $this->mock(BrandingCacheService::class),
            $this->mock(DomainValidationService::class),
            $this->mock(EmailTemplateService::class)
        );

        $this->organization = Organization::factory()->create();
        $this->config = WhiteLabelConfig::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_get_or_create_config_returns_existing_config()
    {
        $result = $this->service->getOrCreateConfig($this->organization);

        $this->assertEquals($this->config->id, $result->id);
        $this->assertDatabaseCount('white_label_configs', 1);
    }

    public function test_get_or_create_config_creates_new_config()
    {
        $newOrg = Organization::factory()->create();

        $result = $this->service->getOrCreateConfig($newOrg);

        $this->assertEquals($newOrg->id, $result->organization_id);
        $this->assertDatabaseHas('white_label_configs', [
            'organization_id' => $newOrg->id,
        ]);
    }

    public function test_process_logo_validates_and_stores_image()
    {
        $file = UploadedFile::fake()->image('logo.png', 300, 100);

        $result = $this->service->processLogo($file, $this->organization);

        $this->assertStringContainsString('branding/logos', $result);
        Storage::disk('public')->assertExists("branding/logos/{$this->organization->id}");
    }

    public function test_process_logo_rejects_invalid_file_types()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        $this->service->processLogo($file, $this->organization);
    }

    public function test_process_logo_rejects_large_files()
    {
        $file = UploadedFile::fake()->image('logo.png')->size(6000); // 6MB

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds 5MB limit');

        $this->service->processLogo($file, $this->organization);
    }

    public function test_compile_theme_generates_css_variables()
    {
        $result = $this->service->compileTheme($this->config);

        $this->assertStringContainsString(':root {', $result);
        $this->assertStringContainsString('--primary-color:', $result);
        $this->assertStringContainsString('--secondary-color:', $result);
    }

    public function test_compile_theme_includes_custom_css()
    {
        $this->config->custom_css = '.custom-class { color: red; }';
        $this->config->save();

        $result = $this->service->compileTheme($this->config);

        $this->assertStringContainsString('.custom-class { color: red; }', $result);
    }

    public function test_compile_theme_generates_dark_mode_styles()
    {
        $this->config->theme_config = ['enable_dark_mode' => true];
        $this->config->save();

        $result = $this->service->compileTheme($this->config);

        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $result);
        $this->assertStringContainsString('.dark {', $result);
    }

    public function test_set_custom_domain_validates_domain()
    {
        $domainService = $this->mock(DomainValidationService::class);
        $domainService->shouldReceive('validateDns')
            ->once()
            ->andReturn(['valid' => true]);
        $domainService->shouldReceive('validateSsl')
            ->once()
            ->andReturn(['valid' => true]);

        $service = new WhiteLabelService(
            $this->mock(BrandingCacheService::class),
            $domainService,
            $this->mock(EmailTemplateService::class)
        );

        $result = $service->setCustomDomain($this->config, 'example.com');

        $this->assertTrue($result['success']);
        $this->assertContains('example.com', $this->config->fresh()->custom_domains);
    }

    public function test_export_configuration_returns_correct_data()
    {
        $this->config->update([
            'platform_name' => 'Test Platform',
            'theme_config' => ['primary_color' => '#ff0000'],
            'custom_css' => '.test { color: blue; }',
        ]);

        $result = $this->service->exportConfiguration($this->config);

        $this->assertEquals('Test Platform', $result['platform_name']);
        $this->assertEquals(['primary_color' => '#ff0000'], $result['theme_config']);
        $this->assertEquals('.test { color: blue; }', $result['custom_css']);
        $this->assertEquals('1.0', $result['version']);
    }

    public function test_import_configuration_updates_config()
    {
        $importData = [
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'platform_name' => 'Imported Platform',
            'theme_config' => ['primary_color' => '#00ff00'],
        ];

        $this->service->importConfiguration($this->config, $importData);

        $this->config->refresh();
        $this->assertEquals('Imported Platform', $this->config->platform_name);
        $this->assertEquals('#00ff00', $this->config->theme_config['primary_color']);
    }

    public function test_hex_to_rgb_conversion()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hexToRgb');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '#ff0000');
        $this->assertEquals('255, 0, 0', $result);

        $result = $method->invoke($this->service, '#00ff00');
        $this->assertEquals('0, 255, 0', $result);

        $result = $method->invoke($this->service, '#fff');
        $this->assertEquals('255, 255, 255', $result);
    }

    public function test_adjust_color_brightness()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('adjustColorBrightness');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '#808080', 20);
        $this->assertEquals('#999999', $result);

        $result = $method->invoke($this->service, '#808080', -20);
        $this->assertEquals('#666666', $result);
    }

    public function test_minify_css_removes_unnecessary_characters()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('minifyCss');
        $method->setAccessible(true);

        $css = "
            /* Comment */
            .test {
                color: red;
                background: blue;
            }
        ";

        $result = $method->invoke($this->service, $css);

        $this->assertStringNotContainsString('/* Comment */', $result);
        $this->assertStringNotContainsString("\n", $result);
        $this->assertStringContainsString('.test{color:red;background:blue;}', $result);
    }
}