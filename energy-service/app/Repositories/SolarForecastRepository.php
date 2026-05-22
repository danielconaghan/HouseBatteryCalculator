<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Solar\DailySolarForecastDTO;
use App\Models\SolarForecast;
use App\Repositories\Contracts\SolarForecastRepositoryInterface;
use Carbon\Carbon;

class SolarForecastRepository implements SolarForecastRepositoryInterface
{
    public function storeForDate(
        Carbon $forecastDate,
        float  $estimatedKwh,
        float  $radiationKwhM2,
        ?int   $cloudCoverPct,
    ): void {
        SolarForecast::updateOrCreate(
            ['forecast_date' => $forecastDate->toDateString()],
            [
                'estimated_kwh'    => $estimatedKwh,
                'radiation_kwh_m2' => $radiationKwhM2,
                'cloud_cover_pct'  => $cloudCoverPct,
                'generated_at'     => Carbon::now(),
            ],
        );
    }

    public function forDate(Carbon $date): ?DailySolarForecastDTO
    {
        $row = SolarForecast::whereDate('forecast_date', $date->toDateString())->first();

        if ($row === null) {
            return null;
        }

        return new DailySolarForecastDTO(
            forecast_date:    Carbon::instance($row->forecast_date),
            estimated_kwh:    $row->estimated_kwh,
            radiation_kwh_m2: $row->radiation_kwh_m2,
            cloud_cover_pct:  $row->cloud_cover_pct,
            generated_at:     Carbon::instance($row->generated_at),
        );
    }
}
