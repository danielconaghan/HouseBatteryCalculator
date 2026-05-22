<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\RecommendationDTO;

interface EnergyServiceClientInterface
{
    public function getRecommendation(): RecommendationDTO;
}
