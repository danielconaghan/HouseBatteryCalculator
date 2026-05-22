<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarForecast extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'forecast_date',
        'estimated_kwh',
        'radiation_kwh_m2',
        'cloud_cover_pct',
        'generated_at',
    ];

    protected $casts = [
        'forecast_date'    => 'date',
        'estimated_kwh'    => 'float',
        'radiation_kwh_m2' => 'float',
        'cloud_cover_pct'  => 'integer',
        'generated_at'     => 'datetime',
    ];
}
