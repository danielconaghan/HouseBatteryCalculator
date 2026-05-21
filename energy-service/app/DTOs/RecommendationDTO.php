<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RecommendationAction;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class RecommendationDTO extends Data
{
    public function __construct(
        public readonly RecommendationAction $action,
        public readonly int $target_charge_pct,
        public readonly float $target_charge_kwh,
        /** Confidence in this recommendation, from 0 (none) to 1 (full). */
        public readonly float $confidence,
        public readonly ReasoningDTO $reasoning,
        public readonly Carbon $generated_at,
        public readonly Carbon $valid_until,
    ) {}
}
