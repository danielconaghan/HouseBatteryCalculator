<?php

declare(strict_types=1);

namespace App\DTOs\ForecastSolar;

use Spatie\LaravelData\Data;

/**
 * Describes one physical array plane for a Forecast.solar API call.
 * Constructed from config('solar.arrays') entries before calling the client.
 */
class SolarArrayDTO extends Data
{
    public function __construct(
        public readonly string $name,

        /** Peak power of this array in kWp */
        public readonly float $kwp,

        /** Azimuth angle in degrees (0 = North, 90 = East, 180 = South, 270 = West) */
        public readonly int $azimuth,

        /** Tilt angle from horizontal in degrees */
        public readonly int $tilt,
    ) {}
}
