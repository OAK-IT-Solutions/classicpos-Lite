<?php

namespace App\Providers;

use App\Http\Middleware\TenantResolution;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class LandlordServiceProvider extends ServiceProvider
{
    /**
     * Register landlord services.
     */
    public function register(): void
    {
        // Bind TenantService as singleton
        $this->app->singleton(\App\Services\TenantService::class);

        // Bind PesapalService as singleton
        $this->app->singleton(\App\Services\PesapalService::class);

        // Bind the current tenant in the container
        $this->app->bind('currentTenant', function () {
            return request()->attributes->get('tenant');
        });
    }

    /**
     * Bootstrap landlord services.
     */
    public function boot(): void
    {
        // Use custom PersonalAccessToken model that searches both landlord and tenant connections
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/landlord.php' => config_path('landlord.php'),
        ], 'landlord-config');

        // Publish landlord migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations/landlord' => database_path('migrations/landlord'),
        ], 'landlord-migrations');
    }
}
