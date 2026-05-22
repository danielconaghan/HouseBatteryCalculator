<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\Contracts\ConsumptionReadingRepositoryInterface;
use App\Services\Contracts\OctopusClientInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchConsumptionJob implements ShouldQueue
{
    use Queueable;

    /**
     * On first run there is no latest reading, so we backfill this many days
     * to ensure the assembler always has enough history.
     */
    private const int INITIAL_BACKFILL_DAYS = 45;

    public int $tries   = 3;
    public int $backoff = 120;

    public function handle(
        OctopusClientInterface               $octopus,
        ConsumptionReadingRepositoryInterface $repository,
    ): void {
        $latest = $repository->latestIntervalEnd();

        // On first run backfill 45 days; on subsequent runs fetch from last known point
        $from = $latest !== null
            ? $latest->copy()->subHour()  // one-hour overlap to catch any late-arriving data
            : Carbon::now()->subDays(self::INITIAL_BACKFILL_DAYS)->startOfDay();

        $to = Carbon::now();

        $readings = $octopus->getHalfHourlyConsumption($from, $to);

        if (empty($readings)) {
            Log::info('FetchConsumptionJob: no new readings', [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ]);
            return;
        }

        $repository->upsertBatch($readings);

        Log::info('Consumption readings stored', [
            'count' => count($readings),
            'from'  => $from->toIso8601String(),
            'to'    => $to->toIso8601String(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('FetchConsumptionJob failed', ['error' => $e->getMessage()]);
    }
}
