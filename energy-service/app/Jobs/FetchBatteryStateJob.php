<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\Contracts\BatteryReadingRepositoryInterface;
use App\Services\Contracts\SolaxClientInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchBatteryStateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function handle(
        SolaxClientInterface              $solax,
        BatteryReadingRepositoryInterface $repository,
    ): void {
        $dto = $solax->getBatteryState();
        $repository->store($dto);

        Log::info('Battery state fetched', [
            'charge_pct' => $dto->charge_pct,
            'charge_kwh' => $dto->charge_kwh,
            'bat_power_w' => $dto->bat_power_w,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('FetchBatteryStateJob failed', ['error' => $e->getMessage()]);
    }
}
