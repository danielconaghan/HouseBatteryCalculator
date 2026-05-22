<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\Contracts\SolarForecastRepositoryInterface;
use App\Services\Contracts\OpenMeteoClientInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchSolarForecastJob implements ShouldQueue
{
    use Queueable;

    /**
     * PV system performance ratio — accounts for inverter efficiency, wiring
     * losses, and temperature derating for this installation.
     */
    private const float PERFORMANCE_RATIO = 0.78;

    public int $tries   = 3;
    public int $backoff = 300;

    public function __construct(private readonly float $totalKwp) {}

    public function handle(
        OpenMeteoClientInterface         $openMeteo,
        SolarForecastRepositoryInterface $repository,
    ): void {
        $tomorrow = Carbon::tomorrow();
        $weather  = $openMeteo->getDailyForecast($tomorrow);

        $radiationKwhM2 = round($weather->shortwave_radiation_mj / 3.6, 4);
        $estimatedKwh   = round($this->totalKwp * $radiationKwhM2 * self::PERFORMANCE_RATIO, 3);

        $repository->storeForDate(
            forecastDate:  $tomorrow,
            estimatedKwh:  $estimatedKwh,
            radiationKwhM2: $radiationKwhM2,
            cloudCoverPct:  (int) round($weather->cloud_cover_pct),
        );

        Log::info('Solar forecast stored', [
            'forecast_date'    => $tomorrow->toDateString(),
            'estimated_kwh'    => $estimatedKwh,
            'radiation_kwh_m2' => $radiationKwhM2,
            'cloud_cover_pct'  => $weather->cloud_cover_pct,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('FetchSolarForecastJob failed', ['error' => $e->getMessage()]);
    }
}
