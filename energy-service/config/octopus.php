<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Octopus Energy API Configuration
    |--------------------------------------------------------------------------
    |
    | Intelligent Octopus Go tariff. Cheap rate window: 23:30–05:30.
    | Rates are in pence per kWh and are config values — never hardcoded.
    |
    */

    'api_key'        => env('OCTOPUS_API_KEY'),
    'account_number' => env('OCTOPUS_ACCOUNT_NUMBER'),
    'mpan'           => env('OCTOPUS_MPAN'),
    'serial_number'  => env('OCTOPUS_SERIAL_NUMBER'),
    'base_url'       => env('OCTOPUS_BASE_URL', 'https://api.octopus.energy'),

    'tariff' => [
        'cheap_rate_pence' => (float) env('OCTOPUS_CHEAP_RATE_PENCE', 7.5),
        'day_rate_pence'   => (float) env('OCTOPUS_DAY_RATE_PENCE', 24.0),
        'cheap_start'      => env('OCTOPUS_CHEAP_START', '23:30'),
        'cheap_end'        => env('OCTOPUS_CHEAP_END', '05:30'),
    ],

];
