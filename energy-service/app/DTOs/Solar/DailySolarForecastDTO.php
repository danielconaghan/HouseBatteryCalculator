<?php

declare(strict_types=1);

namespace App\DTOs\Solar;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class DailySolarForecastDTO extends Data
{
    public function __construct(
        public readonly Carbon $forecast_date,
        public readonly float  $estimated_kwh,
        public readonly float  $radiation_kwh_m2,
        public readonly ?int   $cloud_cover_pct,
        public readonly Carbon $generated_at,
    ) {}
}
