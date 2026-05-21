<?php

declare(strict_types=1);

namespace App\DTOs\OpenMeteo;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class WeatherForecastDTO extends Data
{
    public function __construct(
        /**
         * Mean cloud cover for the day as a percentage (0–100).
         * Mapped directly to cloud_cover_pct in RecommendationInputDTO.
         */
        public readonly float $cloud_cover_pct,

        /**
         * Total solar irradiance for the day in MJ/m².
         * Used to compute generation_forecast_divergence against Forecast.solar:
         * a large discrepancy between irradiance-implied generation and the
         * Forecast.solar estimate degrades recommendation confidence.
         */
        public readonly float $shortwave_radiation_mj,

        public readonly Carbon $date,
    ) {}
}
