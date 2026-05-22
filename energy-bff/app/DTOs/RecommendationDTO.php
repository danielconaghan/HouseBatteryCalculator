<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RecommendationAction;
use Spatie\LaravelData\Data;

class RecommendationDTO extends Data
{
    public function __construct(
        public readonly RecommendationAction $action,
        public readonly int $target_charge_pct,
        public readonly float $target_charge_kwh,
        public readonly float $confidence,
        public readonly ReasoningDTO $reasoning,
        public readonly string $generated_at,
        public readonly string $valid_until,
    ) {}
}
