<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Octopus\ConsumptionReadingDTO;
use Carbon\Carbon;

interface ConsumptionReadingRepositoryInterface
{
    /** @param ConsumptionReadingDTO[] $readings */
    public function upsertBatch(array $readings): void;

    /**
     * @return ConsumptionReadingDTO[]
     */
    public function getSince(Carbon $from): array;

    public function latestIntervalEnd(): ?Carbon;
}
