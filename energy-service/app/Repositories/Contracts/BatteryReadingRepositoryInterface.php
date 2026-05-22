<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Solax\BatteryStateDTO;

interface BatteryReadingRepositoryInterface
{
    public function store(BatteryStateDTO $dto): void;

    public function latest(): ?BatteryStateDTO;
}
