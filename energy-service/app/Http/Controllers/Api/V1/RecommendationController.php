<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CalculateChargeRecommendationAction;
use App\Http\Resources\RecommendationResource;
use App\Services\RecommendationInputAssembler;
use Carbon\Carbon;

class RecommendationController
{
    public function __construct(
        private readonly RecommendationInputAssembler        $assembler,
        private readonly CalculateChargeRecommendationAction $action,
    ) {}

    public function __invoke(): RecommendationResource
    {
        $input          = $this->assembler->assemble(Carbon::tomorrow());
        $recommendation = $this->action->execute($input);

        return RecommendationResource::make($recommendation);
    }
}
