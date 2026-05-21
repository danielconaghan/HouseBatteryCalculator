<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Solax\BatteryStateDTO;

/**
 * Contract for the SolaxCloud REST API.
 * Inverter: SolaX X1-HYBRID-6.0-D (6kW)
 * Battery:  2× SolaX T-BAT H 5.8 (11.6 kWh total)
 */
interface SolaxClientInterface
{
    /**
     * Fetch the current battery state of charge from the inverter.
     * Called once per recommendation cycle to seed RecommendationInputDTO.
     */
    public function getBatteryState(): BatteryStateDTO;
}
