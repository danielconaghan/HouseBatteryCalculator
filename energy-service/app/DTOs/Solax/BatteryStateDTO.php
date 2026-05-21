<?php

declare(strict_types=1);

namespace App\DTOs\Solax;

use App\Enums\InverterStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class BatteryStateDTO extends Data
{
    public function __construct(
        /** State of charge as a percentage (0–100), as reported by the inverter */
        public readonly int $charge_pct,

        /**
         * State of charge in kWh.
         * Derived by the client: soc_pct / 100 * total_capacity_kwh (11.6 kWh).
         * Callers must not recompute this.
         */
        public readonly float $charge_kwh,

        /**
         * Battery terminal power in Watts.
         * Positive = charging, negative = discharging, null = not available.
         */
        public readonly ?float $bat_power_w,

        /**
         * Parsed inverter operating status.
         * Null when the raw status code is not in the InverterStatus enum
         * (i.e. an undocumented code from a firmware update).
         */
        public readonly ?InverterStatus $inverter_status,

        /** Raw status string from the API, preserved for logging. */
        public readonly string $inverter_status_raw,

        /** When this reading was fetched from SolaxCloud */
        public readonly Carbon $fetched_at,
    ) {}
}
