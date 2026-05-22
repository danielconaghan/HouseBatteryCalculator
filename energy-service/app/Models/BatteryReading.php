<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatteryReading extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'charge_pct',
        'charge_kwh',
        'bat_power_w',
        'inverter_status',
        'inverter_status_raw',
        'fetched_at',
    ];

    protected $casts = [
        'charge_pct'          => 'integer',
        'charge_kwh'          => 'float',
        'bat_power_w'         => 'float',
        'fetched_at'          => 'datetime',
    ];
}
