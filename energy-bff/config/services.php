<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Upstream Service URLs
    |--------------------------------------------------------------------------
    |
    | Base URLs for services this BFF aggregates.
    | All values come from environment variables — never hardcoded.
    |
    */

    'energy_service' => [
        'base_url' => env('ENERGY_SERVICE_URL', 'http://energy-service:8000'),
        'timeout'  => (int) env('ENERGY_SERVICE_TIMEOUT', 10),
    ],

];
