<?php

use App\Http\Controllers\Enterprise\DynamicAssetController;
use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use App\Services\Enterprise\WhiteLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use ScssPhp\ScssPhp\Compiler;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Use container resolution to get controller with all dependencies
    $this->controller = app(DynamicAssetController::class);
});

it('compiles SASS with organization variables', function () {
    $themeVariables = [
        'primary_color' => '#ff0000',
        'secondary_color' => '#00ff00',
        'font_family' => 'Arial, sans-serif',
    ];

    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('compileSass');
    $method->setAccessible(true);

    $css = $method->invoke($this->controller, $themeVariables, '');

    expect($css)
        ->toContain('--color-primary')
        ->toContain('#ff0000')
        ->toContain('--font-family-primary');
});

it('generates CSS custom properties correctly', function () {
    $config = [
        'primary_color' => '#ff0000',
        'secondary_color' => '#00ff00',
        'accent_color' => '#0000ff',
    ];

    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('generateCssVariables');
    $method->setAccessible(true);

    $variables = $method->invoke($this->controller, $config);

    expect($variables)
        ->toContain(':root {')
        ->toContain('--primary-color: #ff0000')
        ->toContain('--secondary-color: #00ff00')
        ->toContain('--accent-color: #0000ff');
});

it('generates correct cache key', function () {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('getCacheKey');
    $method->setAccessible(true);

    $cacheKey = $method->invoke($this->controller, 'test-org', 1234567890);

    expect($cacheKey)->toBe('branding:test-org:css:v1:1234567890');
});

it('generates correct ETag', function () {
    $config = [
        'primary_color' => '#ff0000',
        'secondary_color' => '#00ff00',
    ];
    $customCss = 'body { margin: 0; }';

    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('generateEtag');
    $method->setAccessible(true);

    $etag1 = $method->invoke($this->controller, $config, $customCss);
    $etag2 = $method->invoke($this->controller, $config, $customCss);

    expect($etag1)->toBe($etag2);
    expect($etag1)->toStartWith('"');
    expect($etag1)->toEndWith('"');
});

it('formats SASS values correctly', function () {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('formatSassValue');
    $method->setAccessible(true);

    // Color values should be returned as-is
    expect($method->invoke($this->controller, '#ff0000'))->toBe('#ff0000');
    expect($method->invoke($this->controller, '#abc'))->toBe('#abc');

    // Font families with commas should be quoted
    expect($method->invoke($this->controller, 'Arial, sans-serif'))
        ->toBe('"Arial, sans-serif"');

    // Already quoted strings should be returned as-is
    expect($method->invoke($this->controller, '"Arial, sans-serif"'))
        ->toBe('"Arial, sans-serif"');
});

it('returns default CSS when compilation fails', function () {
    // Set config before calling getDefaultCss using Config facade
    Config::set('enterprise.white_label.default_theme', [
        'primary_color' => '#3b82f6',
        'secondary_color' => '#1f2937',
    ]);

    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('getDefaultCss');
    $method->setAccessible(true);

    $defaultCss = $method->invoke($this->controller);

    expect($defaultCss)
        ->toContain(':root {')
        ->toContain('--primary-color');
});
