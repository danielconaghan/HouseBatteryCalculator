<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\RecommendationResource;
use App\Services\Contracts\EnergyServiceClientInterface;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationController
{
    public function __construct(
        private readonly EnergyServiceClientInterface $energyService,
    ) {}

    public function __invoke(): JsonResource
    {
        return new RecommendationResource(
            $this->energyService->getRecommendation(),
        );
    }
}
