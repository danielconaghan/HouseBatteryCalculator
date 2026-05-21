<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Solar Installation Configuration
    |--------------------------------------------------------------------------
    |
    | Panel array definitions used for Forecast.solar API calls.
    | Each array is a separate plane with its own azimuth, tilt, and kWp.
    |
    | Location: 51.7°N, 2.2°W — Nailsworth, Gloucestershire.
    | All panels: Eurener MEPV 400W, tilt 35°.
    |
    | Group 5 azimuth is TBC — update SOLAR_GROUP5_AZIMUTH when confirmed.
    |
    */

    'location' => [
        'latitude'  => (float) env('SOLAR_LATITUDE', 51.7),
        'longitude' => (float) env('SOLAR_LONGITUDE', -2.2),
    ],

    'arrays' => [
        [
            'name'    => 'Group 1 — South (2 panels)',
            'kwp'     => 0.8,
            'azimuth' => 181,
            'tilt'    => 35,
        ],
        [
            'name'    => 'Group 2 — East (7 panels)',
            'kwp'     => 2.8,
            'azimuth' => 90,
            'tilt'    => 35,
        ],
        [
            'name'    => 'Group 3 — West (3 panels)',
            'kwp'     => 1.2,
            'azimuth' => 270,
            'tilt'    => 35,
        ],
        [
            'name'    => 'Group 4 — South (6 panels)',
            'kwp'     => 2.4,
            'azimuth' => 181,
            'tilt'    => 35,
        ],
        [
            'name'    => 'Group 5 — Unknown (1 panel)',
            'kwp'     => 0.4,
            'azimuth' => (int) env('SOLAR_GROUP5_AZIMUTH', 181),
            'tilt'    => 35,
        ],
    ],

    'total_kwp' => 7.6,

];
