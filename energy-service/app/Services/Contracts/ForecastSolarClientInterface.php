<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\ForecastSolar\SolarArrayDTO;
use App\DTOs\ForecastSolar\SolarForecastDTO;
use Carbon\Carbon;

/**
 * Contract for the Forecast.solar estimation API.
 *
 * The API requires one request per array plane. The installation location
 * (lat/lon) is injected into the concrete implementation via the service
 * provider — it is not part of this interface because it never varies.
 *
 * No API key is required for the free tier.
 * Endpoint: GET https://api.forecast.solar/estimate/:lat/:lon/:dec/:az/:kwp
 */
interface ForecastSolarClientInterface
{
    /**
     * Fetch the estimated daily solar generation for a single array plane.
     *
     * Call once per array defined in config('solar.arrays') and sum the results
     * to produce forecast_generation_kwh for RecommendationInputDTO.
     */
    public function getDailyForecast(SolarArrayDTO $array, Carbon $date): SolarForecastDTO;
}
