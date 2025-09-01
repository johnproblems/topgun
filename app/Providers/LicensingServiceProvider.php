<?php

namespace App\Providers;

use App\Contracts\LicensingServiceInterface;
use App\Services\LicensingService;
use Illuminate\Support\ServiceProvider;

class LicensingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LicensingServiceInterface::class, LicensingService::class);

        $this->app->singleton('licensing', function ($app) {
            return $app->make(LicensingServiceInterface::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
