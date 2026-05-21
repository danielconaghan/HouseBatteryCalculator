<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ForecastSolar\SolarArrayDTO;
use App\DTOs\ForecastSolar\SolarForecastDTO;
use App\Exceptions\ForecastSolarApiException;
use App\Services\Contracts\ForecastSolarClientInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ForecastSolarClient implements ForecastSolarClientInterface
{
    private const string BASE_URL = 'https://api.forecast.solar';

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
    ) {}

    public function getDailyForecast(SolarArrayDTO $array, Carbon $date): SolarForecastDTO
    {
        $response = Http::get($this->buildUrl($array));

        if (!$response->successful()) {
            throw new ForecastSolarApiException(
                "Forecast.solar HTTP error {$response->status()} for array '{$array->name}'",
            );
        }

        $body = $response->json();

        if (($body['message']['code'] ?? -1) !== 0) {
            $text = $body['message']['text'] ?? 'unknown error';
            throw new ForecastSolarApiException(
                "Forecast.solar API error for array '{$array->name}': {$text}",
            );
        }

        return $this->buildDto($array, $date, $body['result']);
    }

    private function buildUrl(SolarArrayDTO $array): string
    {
        // Forecast.solar azimuth is degrees from South (South=0, East=−90, West=+90).
        // Our config stores compass bearing. Convert: api_az = compass_bearing − 180.
        $apiAzimuth = $array->azimuth - 180;

        return sprintf(
            '%s/estimate/%s/%s/%d/%d/%s',
            self::BASE_URL,
            $this->latitude,
            $this->longitude,
            $array->tilt,
            $apiAzimuth,
            $array->kwp,
        );
    }

    private function buildDto(SolarArrayDTO $array, Carbon $date, array $result): SolarForecastDTO
    {
        $dateKey     = $date->toDateString();
        $dailyWh     = $result['watt_hours_day'][$dateKey] ?? null;

        if ($dailyWh === null) {
            throw new ForecastSolarApiException(
                "Forecast.solar returned no data for date {$dateKey} for array '{$array->name}'",
            );
        }

        $periodPrefix = $dateKey.' ';
        $periodData   = array_filter(
            $result['watt_hours_period'] ?? [],
            fn (string $k) => str_starts_with($k, $periodPrefix),
            ARRAY_FILTER_USE_KEY,
        );

        return new SolarForecastDTO(
            array:                $array,
            forecast_kwh:         round($dailyWh / 1000, 3),
            date:                 $date->startOfDay()->clone(),
            watt_hours_by_period: $periodData,
        );
    }
}
