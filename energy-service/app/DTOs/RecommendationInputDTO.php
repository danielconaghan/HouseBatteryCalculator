<?php

declare(strict_types=1);

namespace App\DTOs;

use Spatie\LaravelData\Data;

class RecommendationInputDTO extends Data
{
    public function __construct(
        /** Current battery charge in kWh, from latest stored reading */
        public readonly float $current_battery_kwh,

        /** Current battery charge as a percentage, from latest stored reading */
        public readonly int $current_battery_pct,

        /** Total forecast solar generation for tomorrow in kWh, from stored forecast */
        public readonly float $forecast_generation_kwh,

        /** Expected consumption for tomorrow in kWh, derived from stored history */
        public readonly float $forecast_consumption_kwh,

        /** Cloud cover forecast as a percentage (0–100), from stored forecast */
        public readonly float $cloud_cover_pct,

        /**
         * Coefficient of variation for consumption on this day-of-week.
         * Computed as: std_dev / mean. A value of 0.4 means high variance.
         */
        public readonly float $consumption_variance_coefficient,

        /**
         * Data staleness factor (0.0–1.0).
         * 0.0 = all inputs are fresh; increases by ~0.33 for each stale data source
         * (battery, solar forecast, consumption). Used to degrade confidence when
         * scheduled jobs have not run recently.
         */
        public readonly float $data_staleness_factor,
    ) {}
}
