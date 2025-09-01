<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| License Management Routes
|--------------------------------------------------------------------------
|
| Routes for license validation, purchase, renewal, and management
|
*/

Route::middleware(['auth'])->group(function () {
    // License status and information pages
    Route::get('/license/required', function () {
        return view('license.required');
    })->name('license.required');

    Route::get('/license/invalid', function () {
        return view('license.invalid');
    })->name('license.invalid');

    Route::get('/license/upgrade', function () {
        return view('license.upgrade');
    })->name('license.upgrade');

    // License management actions (to be implemented)
    Route::get('/license/purchase', function () {
        return redirect()->route('dashboard')->with('info', 'License purchase functionality coming soon.');
    })->name('license.purchase');

    Route::get('/license/renew', function () {
        return redirect()->route('dashboard')->with('info', 'License renewal functionality coming soon.');
    })->name('license.renew');

    Route::get('/license/contact', function () {
        return redirect()->route('dashboard')->with('info', 'License support contact functionality coming soon.');
    })->name('license.contact');

    Route::get('/license/compare', function () {
        return redirect()->route('dashboard')->with('info', 'License plan comparison functionality coming soon.');
    })->name('license.compare');

    // License Management Interface
    Route::get('/license/management', function () {
        return view('license.management');
    })->name('license.management');

    // Organization setup (referenced in middleware)
    Route::get('/organization/setup', function () {
        return redirect()->route('dashboard')->with('info', 'Organization setup functionality coming soon.');
    })->name('organization.setup');
});
