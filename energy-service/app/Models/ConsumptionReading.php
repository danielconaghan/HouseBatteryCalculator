<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumptionReading extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'interval_start',
        'interval_end',
        'consumption_kwh',
    ];

    protected $casts = [
        'interval_start'  => 'datetime',
        'interval_end'    => 'datetime',
        'consumption_kwh' => 'float',
    ];
}
