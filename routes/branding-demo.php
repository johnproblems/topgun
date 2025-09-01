<?php

use App\Http\Controllers\DynamicAssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dynamic Branding Demo Routes
|--------------------------------------------------------------------------
|
| These routes demonstrate how single-instance multi-domain branding works.
| The same routes serve different content based on the requesting domain.
|
*/

// Dynamic asset routes
Route::get('/css/dynamic-theme.css', [DynamicAssetController::class, 'dynamicCss'])
    ->name('dynamic.css');

Route::get('/favicon.ico', [DynamicAssetController::class, 'dynamicFavicon'])
    ->name('dynamic.favicon');

// Debug route to see how domain detection works
Route::get('/debug/branding', [DynamicAssetController::class, 'debugBranding'])
    ->name('debug.branding');

// Demo page that shows different branding
Route::get('/branding-demo', function () {
    return view('branding-demo');
})->name('branding.demo');

// API endpoint that returns different data based on domain
Route::get('/api/branding-info', function () {
    $branding = app('current.branding');

    return response()->json([
        'platform_name' => $branding?->getPlatformName() ?? 'Coolify',
        'domain' => request()->getHost(),
        'has_custom_branding' => $branding !== null,
        'theme_primary_color' => $branding?->getThemeVariable('primary_color') ?? '#3b82f6',
        'organization_name' => $branding?->organization?->name ?? 'Default Organization',
        'timestamp' => now()->toISOString(),
    ]);
})->name('api.branding.info');
