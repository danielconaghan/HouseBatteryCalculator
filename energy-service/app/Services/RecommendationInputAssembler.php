<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Octopus\ConsumptionReadingDTO;
use App\DTOs\RecommendationInputDTO;
use App\DTOs\Solar\DailySolarForecastDTO;
use App\DTOs\Solax\BatteryStateDTO;
use App\Repositories\Contracts\BatteryReadingRepositoryInterface;
use App\Repositories\Contracts\ConsumptionReadingRepositoryInterface;
use App\Repositories\Contracts\SolarForecastRepositoryInterface;
use Carbon\Carbon;

class RecommendationInputAssembler
{
    /** How many weeks of same-day-of-week history to use for consumption estimate */
    private const int HISTORY_WEEKS = 6;

    /** Default daily consumption (kWh) used when no history is available */
    private const float DEFAULT_CONSUMPTION_KWH = 10.0;

    private const int   BATTERY_STALE_MINUTES = 30;
    private const int   SOLAR_STALE_HOURS     = 25;
    private const int   CONSUMPTION_STALE_HOURS = 3;

    public function __construct(
        private readonly BatteryReadingRepositoryInterface     $batteryRepo,
        private readonly SolarForecastRepositoryInterface      $forecastRepo,
        private readonly ConsumptionReadingRepositoryInterface $consumptionRepo,
    ) {}

    public function assemble(Carbon $forecastDate): RecommendationInputDTO
    {
        $battery     = $this->batteryRepo->latest();
        $forecast    = $this->forecastRepo->forDate($forecastDate);
        $history     = $this->consumptionRepo->getSince(
            $forecastDate->copy()->subWeeks(self::HISTORY_WEEKS)->startOfDay(),
        );

        $dailyTotals    = $this->aggregateDailyConsumption($history, $forecastDate);
        $consumptionKwh = $this->meanOrDefault($dailyTotals);
        $variance       = $this->varianceCoefficient($dailyTotals, $consumptionKwh);
        $staleness      = $this->computeStaleness($battery, $forecast, $history);

        return new RecommendationInputDTO(
            current_battery_kwh:              $battery?->charge_kwh          ?? 0.0,
            current_battery_pct:              $battery?->charge_pct           ?? 0,
            forecast_generation_kwh:          $forecast?->estimated_kwh       ?? 0.0,
            forecast_consumption_kwh:         $consumptionKwh,
            cloud_cover_pct:                  (float) ($forecast?->cloud_cover_pct ?? 100),
            consumption_variance_coefficient: $variance,
            data_staleness_factor:            $staleness,
        );
    }

    /**
     * Groups half-hourly readings by date, keeps only dates whose day-of-week
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
            $key          = $reading->interval_start->toDateString();
            $byDate[$key] = ($byDate[$key] ?? 0.0) + $reading->consumption_kwh;
        }

        return array_values($byDate);
    }

    /** @param float[] $dailyTotals */
    private function meanOrDefault(array $dailyTotals): float
    {
        if (empty($dailyTotals)) {
            return self::DEFAULT_CONSUMPTION_KWH;
        }

        return array_sum($dailyTotals) / count($dailyTotals);
    }

    /**
     * Sample coefficient of variation: std_dev / mean.
     * Returns 0.0 when fewer than two data points exist.
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

    private function computeStaleness(
        ?BatteryStateDTO       $battery,
        ?DailySolarForecastDTO $forecast,
        array                  $consumptionReadings,
    ): float {
        $factor = 0.0;

        if ($battery === null || $battery->fetched_at->isBefore(now()->subMinutes(self::BATTERY_STALE_MINUTES))) {
            $factor += 0.33;
        }

        if ($forecast === null || $forecast->generated_at->isBefore(now()->subHours(self::SOLAR_STALE_HOURS))) {
            $factor += 0.33;
        }

        if (empty($consumptionReadings)) {
            $factor += 0.34;
        } else {
            $latest = end($consumptionReadings);
            if ($latest->interval_end->isBefore(now()->subHours(self::CONSUMPTION_STALE_HOURS))) {
                $factor += 0.34;
            }
        }

        return round(min($factor, 1.0), 2);
    }
}
