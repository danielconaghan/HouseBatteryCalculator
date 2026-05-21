<?php

declare(strict_types=1);

namespace App\DTOs\ForecastSolar;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class SolarForecastDTO extends Data
{
    public function __construct(
        /** The array this forecast was generated for */
        public readonly SolarArrayDTO $array,

        /** Total estimated generation for the day, in kWh */
        public readonly float $forecast_kwh,

        /** The date this forecast covers */
        public readonly Carbon $date,

        /**
         * Watt-hour production by timestamp.
         * Keys are ISO 8601 datetime strings; values are Wh.
         * Used when computing generation_forecast_divergence against Open-Meteo.
         *
         * @var array<string, int>
         */
        public readonly array $watt_hours_by_period,
    ) {}
}
