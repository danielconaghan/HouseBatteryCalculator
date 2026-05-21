<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Battery Configuration — SolaX T-BAT H 5.8 x2
    |--------------------------------------------------------------------------
    |
    | Physical limits and the longevity ceiling for the installed battery bank.
    |
    | BATTERY_CHARGE_CEILING_PCT is a deliberate longevity decision: charging
    | beyond 70% is avoided to extend cell life. It is never a magic number —
    | it is always referenced by name as BATTERY_CHARGE_CEILING_PCT.
    |
    */

    'total_capacity_kwh'  => (float) env('BATTERY_TOTAL_CAPACITY_KWH', 11.6),
    'usable_capacity_kwh' => (float) env('BATTERY_USABLE_CAPACITY_KWH', 8.1),
    'charge_ceiling_pct'  => (int)   env('BATTERY_CHARGE_CEILING_PCT', 70),
    'charge_rate_kw'      => (float) env('BATTERY_CHARGE_RATE_KW', 3.5),

];
