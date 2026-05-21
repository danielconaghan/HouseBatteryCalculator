<?php

declare(strict_types=1);

namespace App\DTOs\Octopus;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ConsumptionReadingDTO extends Data
{
    public function __construct(
        /** Electricity consumed during this half-hour interval, in kWh */
        public readonly float $consumption_kwh,

        public readonly Carbon $interval_start,
        public readonly Carbon $interval_end,
    ) {}
}
