<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OpenMeteo\WeatherForecastDTO;
use App\Exceptions\OpenMeteoApiException;
use App\Services\Contracts\OpenMeteoClientInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class OpenMeteoClient implements OpenMeteoClientInterface
{
    private const string BASE_URL  = 'https://api.open-meteo.com/v1/forecast';
    private const string TIMEZONE  = 'Europe/London';

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
    ) {}

    public function getDailyForecast(Carbon $date): WeatherForecastDTO
    {
        $dateString = $date->toDateString();

        $response = Http::get(self::BASE_URL, [
            'latitude'                 => $this->latitude,
            'longitude'                => $this->longitude,
            'daily'                    => 'cloud_cover_mean,shortwave_radiation_sum',
            'start_date'               => $dateString,
            'end_date'                 => $dateString,
            'timezone'                 => self::TIMEZONE,
        ]);

        if (!$response->successful()) {
            throw new OpenMeteoApiException(
                "Open-Meteo HTTP error {$response->status()}: {$response->body()}",
            );
        }

        return $this->buildDto($date, $response->json());
    }

    private function buildDto(Carbon $date, array $body): WeatherForecastDTO
    {
        $daily = $body['daily'] ?? [];

        $cloudCover  = $daily['cloud_cover_mean'][0] ?? null;
        $irradiance  = $daily['shortwave_radiation_sum'][0] ?? null;

        if ($cloudCover === null || $irradiance === null) {
            throw new OpenMeteoApiException(
                "Open-Meteo response missing expected daily fields for {$date->toDateString()}. ".
                'Received keys: '.implode(', ', array_keys($daily)),
            );
        }

        return new WeatherForecastDTO(
            cloud_cover_pct:       (float) $cloudCover,
            shortwave_radiation_mj: (float) $irradiance,
            date:                  $date->startOfDay()->clone(),
        );
    }
}
