<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind energy-service HTTP client and other aggregators here.
    }

    public function boot(): void
    {
        //
    }
}
