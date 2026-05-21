<?php

declare(strict_types=1);

namespace App\DTOs;

use Spatie\LaravelData\Data;

class RecommendationInputDTO extends Data
{
    public function __construct(
        /** Current battery charge in kWh, from SolaxCloud */
        public readonly float $current_battery_kwh,

        /** Current battery charge as a percentage, from SolaxCloud */
        public readonly int $current_battery_pct,

        /** Total forecast solar generation for tomorrow in kWh, summed across all arrays */
        public readonly float $forecast_generation_kwh,

        /** Expected consumption for tomorrow in kWh, from Octopus history */
        public readonly float $forecast_consumption_kwh,

        /** Cloud cover forecast as a percentage (0–100), from Open-Meteo */
        public readonly float $cloud_cover_pct,

        /**
         * Normalised divergence between Forecast.solar and Open-Meteo implied generation.
         * Computed as: |forecast_solar - openmeteo_estimate| / max(forecast_solar, openmeteo_estimate)
         * A value of 0.3 means the two sources disagree by 30%.
         */
        public readonly float $generation_forecast_divergence,

        /**
         * Coefficient of variation for consumption on this day-of-week and season.
         * Computed as: std_dev / mean. A value of 0.4 means high variance.
         */
        public readonly float $consumption_variance_coefficient,
    ) {}
}
