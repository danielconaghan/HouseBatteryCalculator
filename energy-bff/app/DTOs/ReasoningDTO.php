<?php

declare(strict_types=1);

namespace App\DTOs;

use Spatie\LaravelData\Data;

class ReasoningDTO extends Data
{
    public function __construct(
        public readonly float $forecast_generation_kwh,
        public readonly float $forecast_consumption_kwh,
        public readonly float $current_battery_kwh,
        public readonly float $gap_kwh,
        /** @var string[] */
        public readonly array $factors,
    ) {}
}
