<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Solar\DailySolarForecastDTO;
use Carbon\Carbon;

interface SolarForecastRepositoryInterface
{
    public function storeForDate(
        Carbon $forecastDate,
        float  $estimatedKwh,
        float  $radiationKwhM2,
        ?int   $cloudCoverPct,
    ): void;

    public function forDate(Carbon $date): ?DailySolarForecastDTO;
}
