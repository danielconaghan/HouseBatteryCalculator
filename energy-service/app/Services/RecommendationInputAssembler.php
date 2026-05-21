<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ForecastSolar\SolarArrayDTO;
use App\DTOs\ForecastSolar\SolarForecastDTO;
use App\DTOs\Octopus\ConsumptionReadingDTO;
use App\DTOs\RecommendationInputDTO;
use App\Exceptions\OctopusApiException;
use App\Services\Contracts\ForecastSolarClientInterface;
use App\Services\Contracts\OctopusClientInterface;
use App\Services\Contracts\OpenMeteoClientInterface;
use App\Services\Contracts\SolaxClientInterface;
use Carbon\Carbon;

class RecommendationInputAssembler
{
    /**
     * How many weeks of same-day-of-week history to fetch from Octopus.
     * 6 weeks balances seasonal relevance against statistical noise.
     */
    private const int   HISTORY_WEEKS     = 6;

    /**
     * Performance ratio used when converting Open-Meteo horizontal irradiance
     * (MJ/m²) to implied AC generation. 0.75 is a conservative real-world
     * figure that accounts for angle losses, temperature, and inverter efficiency.
     * Used only for the divergence comparison, not for generation forecasting.
     */
    private const float PERFORMANCE_RATIO = 0.75;

    /** @param SolarArrayDTO[] $solarArrays */
    public function __construct(
        private readonly SolaxClientInterface         $solax,
        private readonly OctopusClientInterface       $octopus,
        private readonly ForecastSolarClientInterface $forecastSolar,
        private readonly OpenMeteoClientInterface     $openMeteo,
        private readonly array                        $solarArrays,
        private readonly float                        $totalKwp,
    ) {}

    public function assemble(Carbon $forecastDate): RecommendationInputDTO
    {
        $battery        = $this->solax->getBatteryState();
        $arrayForecasts = $this->fetchArrayForecasts($forecastDate);
        $generationKwh  = $this->sumGenerationKwh($arrayForecasts);
        $weather        = $this->openMeteo->getDailyForecast($forecastDate);
        $history        = $this->fetchHistoricalConsumption($forecastDate);
        $dailyTotals    = $this->aggregateDailyConsumption($history, $forecastDate);
        $consumptionKwh = $this->mean($dailyTotals);

        $divergence = $this->generationDivergence($generationKwh, $weather->shortwave_radiation_mj);
        $variance   = $this->varianceCoefficient($dailyTotals, $consumptionKwh);

        return new RecommendationInputDTO(
            current_battery_kwh:              $battery->charge_kwh,
            current_battery_pct:              $battery->charge_pct,
            forecast_generation_kwh:          $generationKwh,
            forecast_consumption_kwh:         $consumptionKwh,
            cloud_cover_pct:                  $weather->cloud_cover_pct,
            generation_forecast_divergence:   $divergence,
            consumption_variance_coefficient: $variance,
        );
    }

    /** @return SolarForecastDTO[] */
    private function fetchArrayForecasts(Carbon $date): array
    {
        return array_map(
            fn (SolarArrayDTO $array) => $this->forecastSolar->getDailyForecast($array, $date),
            $this->solarArrays,
        );
    }

    /** @param SolarForecastDTO[] $forecasts */
    private function sumGenerationKwh(array $forecasts): float
    {
        return round(
            array_sum(array_map(fn (SolarForecastDTO $f) => $f->forecast_kwh, $forecasts)),
            3,
        );
    }

    /** @return ConsumptionReadingDTO[] */
    private function fetchHistoricalConsumption(Carbon $forecastDate): array
    {
        return $this->octopus->getHalfHourlyConsumption(
            $forecastDate->copy()->subWeeks(self::HISTORY_WEEKS)->startOfDay(),
            $forecastDate->copy()->subDay()->endOfDay(),
        );
    }

    /**
     * Groups half-hourly readings by date, filters to dates whose day-of-week
     * matches $forecastDate, and returns an array of daily kWh totals.
     *
     * @param  ConsumptionReadingDTO[] $readings
     * @return float[]
     */
    private function aggregateDailyConsumption(array $readings, Carbon $forecastDate): array
    {
        $targetDayOfWeek = $forecastDate->dayOfWeek;
        $byDate          = [];

        foreach ($readings as $reading) {
            if ($reading->interval_start->dayOfWeek !== $targetDayOfWeek) {
                continue;
            }
            $key         = $reading->interval_start->toDateString();
            $byDate[$key] = ($byDate[$key] ?? 0.0) + $reading->consumption_kwh;
        }

        return array_values($byDate);
    }

    /** @param float[] $dailyTotals */
    private function mean(array $dailyTotals): float
    {
        if (empty($dailyTotals)) {
            throw new OctopusApiException(
                'Cannot compute forecast consumption: no historical data found for the '.
                'past '.self::HISTORY_WEEKS.' weeks matching this day of week.',
            );
        }

        return array_sum($dailyTotals) / count($dailyTotals);
    }

    /**
     * Sample coefficient of variation: std_dev / mean.
     * Returns 0.0 when fewer than two data points exist (variance is undefined).
     *
     * @param float[] $dailyTotals
     */
    private function varianceCoefficient(array $dailyTotals, float $mean): float
    {
        if (count($dailyTotals) < 2 || $mean <= 0.0) {
            return 0.0;
        }

        $squaredDeviations = array_map(fn (float $v) => ($v - $mean) ** 2, $dailyTotals);
        $stdDev            = sqrt(array_sum($squaredDeviations) / (count($dailyTotals) - 1));

        return round($stdDev / $mean, 4);
    }

    /**
     * Normalised divergence between Forecast.solar and Open-Meteo implied generation.
     * Open-Meteo gives horizontal irradiance; we convert to implied AC output using
     * total kWp and a performance ratio (see PERFORMANCE_RATIO constant).
     * This is a rough comparison — we check broad agreement, not precision.
     */
    private function generationDivergence(float $forecastSolarKwh, float $radiationMj): float
    {
        // 1 MJ/m² = 0.2778 kWh/m²; multiply by kWp and PR for implied AC output.
        $impliedKwh = $radiationMj * 0.2778 * $this->totalKwp * self::PERFORMANCE_RATIO;
        $max        = max($forecastSolarKwh, $impliedKwh);

        if ($max <= 0.0) {
            return 0.0;
        }

        return round(abs($forecastSolarKwh - $impliedKwh) / $max, 4);
    }
}
