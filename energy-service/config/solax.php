<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SolaxCloud API Configuration (v2)
    |--------------------------------------------------------------------------
    |
    | Inverter: SolaX X1-HYBRID-6.0-D (6kW)
    |
    | tokenId is obtained from SolaxCloud portal → Service → API.
    | It is sent as an HTTP Header on every request, not as a query param.
    |
    | wifi_sn is the Wi-Fi dongle registration number (wifiSn in the API).
    | It is NOT the inverter serial number — find it on the dongle label
    | or in SolaxCloud portal → Device.
    |
    */

    'token_id' => env('SOLAX_TOKEN_ID'),
    'wifi_sn'  => env('SOLAX_WIFI_SN'),
    'base_url' => env('SOLAX_BASE_URL', 'https://global.solaxcloud.com'),

];
