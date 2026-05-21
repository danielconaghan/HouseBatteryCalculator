<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\OpenMeteo\WeatherForecastDTO;
use Carbon\Carbon;

/**
 * Contract for the Open-Meteo weather forecast API.
 *
 * Used as the confidence validation layer: if cloud cover is high, or if
 * Open-Meteo's implied irradiance disagrees significantly with Forecast.solar,
 * confidence in the recommendation is degraded.
 *
 * The installation location (lat/lon) is injected into the concrete
 * implementation via the service provider — it never varies for this system.
 *
 * No API key is required.
 * Endpoint: GET https://api.open-meteo.com/v1/forecast
 */
interface OpenMeteoClientInterface
{
    /**
     * Fetch the daily weather forecast for the installation location.
     * Returns aggregated daily values (mean cloud cover, etc.) for the given date.
     */
    public function getDailyForecast(Carbon $date): WeatherForecastDTO;
}
