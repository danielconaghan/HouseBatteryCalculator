<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Solax\BatteryStateDTO;
use App\Enums\InverterStatus;
use App\Models\BatteryReading;
use App\Repositories\Contracts\BatteryReadingRepositoryInterface;
use Carbon\Carbon;

class BatteryReadingRepository implements BatteryReadingRepositoryInterface
{
    public function store(BatteryStateDTO $dto): void
    {
        BatteryReading::create([
            'charge_pct'          => $dto->charge_pct,
            'charge_kwh'          => $dto->charge_kwh,
            'bat_power_w'         => $dto->bat_power_w,
            'inverter_status'     => $dto->inverter_status?->value,
            'inverter_status_raw' => $dto->inverter_status_raw,
            'fetched_at'          => $dto->fetched_at,
        ]);
    }

    public function latest(): ?BatteryStateDTO
    {
        $row = BatteryReading::orderByDesc('fetched_at')->first();

        if ($row === null) {
            return null;
        }

        return new BatteryStateDTO(
            charge_pct:          $row->charge_pct,
            charge_kwh:          $row->charge_kwh,
            bat_power_w:         $row->bat_power_w,
            inverter_status:     $row->inverter_status !== null
                                     ? InverterStatus::tryFrom($row->inverter_status)
                                     : null,
            inverter_status_raw: $row->inverter_status_raw,
            fetched_at:          Carbon::instance($row->fetched_at),
        );
    }
}
