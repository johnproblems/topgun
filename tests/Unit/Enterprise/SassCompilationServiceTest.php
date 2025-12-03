<?php

use App\Models\WhiteLabelConfig;
use App\Services\Enterprise\SassCompilationService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->service = new SassCompilationService;

    // Ensure mock SASS files exist
    $dir = resource_path('sass/branding');
    if (! File::exists($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    File::put($dir.'/theme.scss', 'body { color: $primary-color; }');
    File::put($dir.'/dark.scss', '.dark { background: black; }');
});

afterEach(function () {
    // Clean up mock files
    File::deleteDirectory(resource_path('sass'));
});

it('compiles sass with variables', function () {
    $config = new WhiteLabelConfig([
        'theme_config' => [
            'primary_color' => '#ff0000',
        ],
    ]);

    $css = $this->service->compile($config);

    expect($css)->toContain('body{color:#ff0000}');
});

it('handles empty theme config', function () {
    $config = new WhiteLabelConfig(['theme_config' => []]);

    File::put(resource_path('sass/branding/theme.scss'), 'body { color: blue; }');

    $css = $this->service->compile($config);

    expect($css)->toContain('body{color:blue}');
});

it('throws exception for missing theme file', function () {
    File::delete(resource_path('sass/branding/theme.scss'));
    $config = new WhiteLabelConfig;

    $this->service->compile($config);
})->throws(Exception::class, 'SASS template not found');

it('throws exception for invalid sass syntax', function () {
    File::put(resource_path('sass/branding/theme.scss'), 'body { color: $unclosed; ');
    $config = new WhiteLabelConfig;

    $this->service->compile($config);
})->throws(Exception::class, 'SASS compilation failed');

it('compiles dark mode sass', function () {
    $css = $this->service->compileDarkMode();
    expect($css)->toContain('.dark{background:black}');
});

it('throws exception for missing dark mode file', function () {
    File::delete(resource_path('sass/branding/dark.scss'));
    $this->service->compileDarkMode();
})->throws(Exception::class, 'Dark mode SASS file not found');

it('correctly formats sass values', function () {
    $config = new WhiteLabelConfig([
        'theme_config' => [
            'primary_color' => '#123456',
            'font_family' => 'Arial, sans-serif',
            'border_radius' => '4px',
            'css_var' => 'var(--some-var)',
        ],
    ]);

    File::put(resource_path('sass/branding/theme.scss'), '
        :root {
            --primary: $primary-color;
            --font: $font-family;
            --radius: $border-radius;
            --other: $css-var;
        }
    ');

    $css = $this->service->compile($config);
    expect($css)->toContain('--primary:#123456')
        ->and($css)->toContain('--font:"Arial, sans-serif"')
        ->and($css)->toContain('--radius:4px')
        ->and($css)->toContain('--other:var(--some-var)');
});
