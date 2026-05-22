<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Octopus\ConsumptionReadingDTO;
use App\Models\ConsumptionReading;
use App\Repositories\Contracts\ConsumptionReadingRepositoryInterface;
use Carbon\Carbon;

class ConsumptionReadingRepository implements ConsumptionReadingRepositoryInterface
{
    /** @param ConsumptionReadingDTO[] $readings */
    public function upsertBatch(array $readings): void
    {
        if (empty($readings)) {
            return;
        }

        $rows = array_map(
            fn (ConsumptionReadingDTO $r) => [
                'interval_start'  => $r->interval_start->toDateTimeString(),
                'interval_end'    => $r->interval_end->toDateTimeString(),
                'consumption_kwh' => $r->consumption_kwh,
            ],
            $readings,
        );

        ConsumptionReading::upsert($rows, ['interval_start'], ['interval_end', 'consumption_kwh']);
    }

    /** @return ConsumptionReadingDTO[] */
    public function getSince(Carbon $from): array
    {
        return ConsumptionReading::where('interval_start', '>=', $from)
            ->orderBy('interval_start')
            ->get()
            ->map(fn (ConsumptionReading $row) => new ConsumptionReadingDTO(
                consumption_kwh: $row->consumption_kwh,
                interval_start:  Carbon::instance($row->interval_start),
                interval_end:    Carbon::instance($row->interval_end),
            ))
            ->all();
    }

    public function latestIntervalEnd(): ?Carbon
    {
        $row = ConsumptionReading::orderByDesc('interval_end')->first();

        return $row !== null ? Carbon::instance($row->interval_end) : null;
    }
}
