<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Octopus\ConsumptionReadingDTO;
use Carbon\Carbon;

/**
 * Contract for the Octopus Energy REST API.
 * Tariff: Intelligent Octopus Go (cheap window 23:30–05:30)
 */
interface OctopusClientInterface
{
    /**
     * Fetch half-hourly consumption readings for a given period.
     *
     * The caller is responsible for requesting enough history to compute both
     * forecast_consumption_kwh and consumption_variance_coefficient for
     * RecommendationInputDTO. Typically 4–8 weeks of the same day-of-week.
     *
     * @return ConsumptionReadingDTO[]
     */
    public function getHalfHourlyConsumption(Carbon $from, Carbon $to): array;
}
