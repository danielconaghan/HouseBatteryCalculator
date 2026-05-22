<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\EnergyServiceClientInterface;
use App\Services\EnergyServiceClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnergyServiceClientInterface::class, function () {
            return new EnergyServiceClient(
                Http::baseUrl(config('services.energy_service.base_url'))
                    ->acceptJson()
                    ->timeout(config('services.energy_service.timeout')),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
