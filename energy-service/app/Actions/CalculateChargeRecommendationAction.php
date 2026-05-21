<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\RecommendationDTO;
use App\DTOs\RecommendationInputDTO;
use App\DTOs\ReasoningDTO;
use App\Enums\RecommendationAction;
use Carbon\Carbon;

/**
 * Pure action: no side effects, no API calls, no database access.
 * Bind via AppServiceProvider, injecting battery config from config('battery').
 */
class CalculateChargeRecommendationAction
{
    private const float CLOUD_COVER_THRESHOLD_PCT       = 60.0;
    private const float CLOUD_COVER_CONFIDENCE_PENALTY  = 0.2;
    private const float DIVERGENCE_THRESHOLD            = 0.3;
    private const float DIVERGENCE_CONFIDENCE_PENALTY   = 0.2;
    private const float VARIANCE_THRESHOLD              = 0.4;
    private const float VARIANCE_CONFIDENCE_PENALTY     = 0.15;
    private const int   VALIDITY_HOURS                  = 8;

    public function __construct(
        private readonly float $batteryCeilingKwh,
        private readonly int   $batteryCeilingPct,
    ) {}

    public function execute(RecommendationInputDTO $input): RecommendationDTO
    {
        $chargeGap  = $this->calculateChargeGap($input);
        $action     = $this->determineAction($chargeGap);
        $confidence = $this->calculateConfidence($input);

        [$targetPct, $targetKwh] = $this->calculateTarget($action, $input, $chargeGap);

        $reasoning = new ReasoningDTO(
            forecast_generation_kwh: $input->forecast_generation_kwh,
            forecast_consumption_kwh: $input->forecast_consumption_kwh,
            current_battery_kwh: $input->current_battery_kwh,
            gap_kwh: $chargeGap,
            factors: $this->buildFactors($input, $chargeGap),
        );

        return new RecommendationDTO(
            action: $action,
            target_charge_pct: $targetPct,
            target_charge_kwh: $targetKwh,
            confidence: $confidence,
            reasoning: $reasoning,
            generated_at: Carbon::now(),
            valid_until: Carbon::now()->addHours(self::VALIDITY_HOURS),
        );
    }

    private function calculateChargeGap(RecommendationInputDTO $input): float
    {
        $expectedNeed = $input->forecast_consumption_kwh - $input->forecast_generation_kwh;

        return $expectedNeed - $input->current_battery_kwh;
    }

    private function determineAction(float $chargeGap): RecommendationAction
    {
        if ($chargeGap <= 0.0) {
            return RecommendationAction::DoNotCharge;
        }

        if ($chargeGap < $this->batteryCeilingKwh * 0.25) {
            return RecommendationAction::PartialCharge;
        }

        return RecommendationAction::Charge;
    }

    private function calculateTarget(
        RecommendationAction $action,
        RecommendationInputDTO $input,
        float $chargeGap,
    ): array {
        return match ($action) {
            RecommendationAction::DoNotCharge => [
                $input->current_battery_pct,
                $input->current_battery_kwh,
            ],
            RecommendationAction::PartialCharge => [
                min(
                    (int) round(($input->current_battery_kwh + $chargeGap) / $this->batteryCeilingKwh * 100),
                    $this->batteryCeilingPct,
                ),
                round($input->current_battery_kwh + $chargeGap, 2),
            ],
            RecommendationAction::Charge => [
                $this->batteryCeilingPct,
                $this->batteryCeilingKwh,
            ],
        };
    }

    private function calculateConfidence(RecommendationInputDTO $input): float
    {
        $confidence = 1.0;

        if ($input->cloud_cover_pct > self::CLOUD_COVER_THRESHOLD_PCT) {
            $confidence -= self::CLOUD_COVER_CONFIDENCE_PENALTY;
        }

        if ($input->generation_forecast_divergence > self::DIVERGENCE_THRESHOLD) {
            $confidence -= self::DIVERGENCE_CONFIDENCE_PENALTY;
        }

        if ($input->consumption_variance_coefficient > self::VARIANCE_THRESHOLD) {
            $confidence -= self::VARIANCE_CONFIDENCE_PENALTY;
        }

        return max(0.0, round($confidence, 2));
    }

    private function buildFactors(RecommendationInputDTO $input, float $chargeGap): array
    {
        $factors = [$this->summaryFactor($input, $chargeGap)];

        if ($input->cloud_cover_pct > self::CLOUD_COVER_THRESHOLD_PCT) {
            $factors[] = sprintf(
                'Cloud cover %.0f%% exceeds %.0f%% threshold — confidence reduced',
                $input->cloud_cover_pct,
                self::CLOUD_COVER_THRESHOLD_PCT,
            );
        }

        if ($input->generation_forecast_divergence > self::DIVERGENCE_THRESHOLD) {
            $factors[] = sprintf(
                'Forecast.solar and Open-Meteo diverge by %.0f%% — confidence reduced',
                $input->generation_forecast_divergence * 100,
            );
        }

        if ($input->consumption_variance_coefficient > self::VARIANCE_THRESHOLD) {
            $factors[] = 'High consumption variance for this day/season — confidence reduced';
        }

        return $factors;
    }

    private function summaryFactor(RecommendationInputDTO $input, float $chargeGap): string
    {
        return sprintf(
            'Forecast: %.2f kWh generation, %.2f kWh consumption, %.2f kWh charge gap',
            $input->forecast_generation_kwh,
            $input->forecast_consumption_kwh,
            $chargeGap,
        );
    }
}
