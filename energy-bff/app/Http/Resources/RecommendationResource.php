<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\RecommendationDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RecommendationDTO $dto */
        $dto = $this->resource;

        return [
            'action'            => $dto->action->value,
            'target_charge_pct' => $dto->target_charge_pct,
            'target_charge_kwh' => $dto->target_charge_kwh,
            'confidence'        => $dto->confidence,
            'reasoning'         => [
                'forecast_generation_kwh'  => $dto->reasoning->forecast_generation_kwh,
                'forecast_consumption_kwh' => $dto->reasoning->forecast_consumption_kwh,
                'current_battery_kwh'      => $dto->reasoning->current_battery_kwh,
                'gap_kwh'                  => $dto->reasoning->gap_kwh,
                'factors'                  => $dto->reasoning->factors,
            ],
            'generated_at' => $dto->generated_at,
            'valid_until'  => $dto->valid_until,
        ];
    }
}
