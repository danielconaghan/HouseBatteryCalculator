<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CalculateChargeRecommendationAction;
use App\DTOs\RecommendationInputDTO;
use App\Enums\RecommendationAction;
use PHPUnit\Framework\TestCase;

class CalculateChargeRecommendationActionTest extends TestCase
{
    private const float CEILING_KWH = 8.1;
    private const int   CEILING_PCT = 70;

    private CalculateChargeRecommendationAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CalculateChargeRecommendationAction(
            batteryCeilingKwh: self::CEILING_KWH,
            batteryCeilingPct: self::CEILING_PCT,
        );
    }

    // ─── Action determination ──────────────────────────────────────────────────

    public function test_recommends_charge_when_gap_exceeds_threshold(): void
    {
        $result = $this->action->execute($this->input(battery: 1.0, consumption: 10.0, generation: 2.0));

        $this->assertSame(RecommendationAction::Charge, $result->action);
        $this->assertSame(self::CEILING_PCT, $result->target_charge_pct);
        $this->assertSame(self::CEILING_KWH, $result->target_charge_kwh);
    }

    public function test_recommends_partial_charge_when_gap_is_small(): void
    {
        // gap = (6 - 2) - 3.5 = 0.5, which is < ceiling*0.25 = 2.025
        $result = $this->action->execute($this->input(battery: 3.5, consumption: 6.0, generation: 2.0));

        $this->assertSame(RecommendationAction::PartialCharge, $result->action);
    }

    public function test_recommends_do_not_charge_when_battery_and_generation_cover_consumption(): void
    {
        // gap = (5 - 6) - 4 = -5 → DoNotCharge
        $result = $this->action->execute($this->input(battery: 4.0, batteryPct: 40, consumption: 5.0, generation: 6.0));

        $this->assertSame(RecommendationAction::DoNotCharge, $result->action);
        $this->assertSame(40, $result->target_charge_pct);
    }

    public function test_do_not_charge_keeps_current_battery_values(): void
    {
        $result = $this->action->execute($this->input(battery: 6.0, batteryPct: 52, consumption: 3.0, generation: 5.0));

        $this->assertSame(RecommendationAction::DoNotCharge, $result->action);
        $this->assertSame(52, $result->target_charge_pct);
        $this->assertEqualsWithDelta(6.0, $result->target_charge_kwh, 0.01);
    }

    public function test_charge_targets_ceiling_values(): void
    {
        $result = $this->action->execute($this->input(battery: 0.0, consumption: 10.0, generation: 0.0));

        $this->assertSame(RecommendationAction::Charge, $result->action);
        $this->assertSame(self::CEILING_PCT, $result->target_charge_pct);
        $this->assertSame(self::CEILING_KWH, $result->target_charge_kwh);
    }

    public function test_zero_gap_is_do_not_charge(): void
    {
        // gap = (3 - 3) - 0 = 0.0 → DoNotCharge
        $result = $this->action->execute($this->input(battery: 0.0, consumption: 3.0, generation: 3.0));

        $this->assertSame(RecommendationAction::DoNotCharge, $result->action);
    }

    // ─── Confidence ───────────────────────────────────────────────────────────

    public function test_full_confidence_when_all_data_fresh_and_clear(): void
    {
        $result = $this->action->execute($this->input());

        $this->assertEqualsWithDelta(1.0, $result->confidence, 0.001);
    }

    public function test_cloud_cover_above_threshold_reduces_confidence(): void
    {
        $result = $this->action->execute($this->input(cloudCover: 75.0));

        $this->assertEqualsWithDelta(0.8, $result->confidence, 0.001);
    }

    public function test_high_variance_reduces_confidence(): void
    {
        $result = $this->action->execute($this->input(variance: 0.5));

        $this->assertEqualsWithDelta(0.85, $result->confidence, 0.001);
    }

    public function test_stale_data_reduces_confidence_proportionally(): void
    {
        // One stale source ≈ factor 0.33 → penalty = 0.33 * 0.3 ≈ 0.099 → confidence ≈ 0.9
        $result = $this->action->execute($this->input(staleness: 0.33));

        $this->assertEqualsWithDelta(0.9, $result->confidence, 0.01);
    }

    public function test_fully_stale_data_applies_max_staleness_penalty(): void
    {
        // factor 1.0 → penalty = 0.3 → confidence = 0.7
        $result = $this->action->execute($this->input(staleness: 1.0));

        $this->assertEqualsWithDelta(0.7, $result->confidence, 0.001);
    }

    public function test_confidence_does_not_go_below_zero(): void
    {
        $result = $this->action->execute($this->input(
            cloudCover: 90.0,
            variance:   0.9,
            staleness:  1.0,
        ));

        $this->assertGreaterThanOrEqual(0.0, $result->confidence);
    }

    // ─── Reasoning ────────────────────────────────────────────────────────────

    public function test_reasoning_contains_summary_factor(): void
    {
        $result = $this->action->execute($this->input(battery: 2.0, consumption: 8.0, generation: 3.0));

        $this->assertStringContainsString('kWh generation', $result->reasoning->factors[0]);
        $this->assertStringContainsString('kWh consumption', $result->reasoning->factors[0]);
        $this->assertStringContainsString('kWh charge gap', $result->reasoning->factors[0]);
    }

    public function test_cloud_cover_factor_included_when_above_threshold(): void
    {
        $result = $this->action->execute($this->input(cloudCover: 80.0));

        $this->assertCount(2, $result->reasoning->factors);
        $this->assertStringContainsString('Cloud cover', $result->reasoning->factors[1]);
    }

    public function test_staleness_factor_included_when_data_is_stale(): void
    {
        $result = $this->action->execute($this->input(staleness: 0.66));

        $this->assertStringContainsString('stale', implode(' ', $result->reasoning->factors));
    }

    // ─── Timestamps ───────────────────────────────────────────────────────────

    public function test_valid_until_is_eight_hours_after_generated_at(): void
    {
        $result = $this->action->execute($this->input());

        $diff = (int) $result->generated_at->diffInHours($result->valid_until);
        $this->assertSame(8, $diff);
    }

    // ─── Reasoning DTO values ─────────────────────────────────────────────────

    public function test_reasoning_dto_reflects_inputs(): void
    {
        $result = $this->action->execute($this->input(battery: 3.0, consumption: 7.0, generation: 2.0));

        $this->assertEqualsWithDelta(2.0, $result->reasoning->forecast_generation_kwh, 0.001);
        $this->assertEqualsWithDelta(7.0, $result->reasoning->forecast_consumption_kwh, 0.001);
        $this->assertEqualsWithDelta(3.0, $result->reasoning->current_battery_kwh, 0.001);
        $this->assertEqualsWithDelta(2.0, $result->reasoning->gap_kwh, 0.001);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function input(
        float $battery    = 4.0,
        int   $batteryPct = 35,
        float $consumption = 8.0,
        float $generation  = 3.0,
        float $cloudCover  = 20.0,
        float $variance    = 0.1,
        float $staleness   = 0.0,
    ): RecommendationInputDTO {
        return new RecommendationInputDTO(
            current_battery_kwh:              $battery,
            current_battery_pct:              $batteryPct,
            forecast_generation_kwh:          $generation,
            forecast_consumption_kwh:         $consumption,
            cloud_cover_pct:                  $cloudCover,
            consumption_variance_coefficient: $variance,
            data_staleness_factor:            $staleness,
        );
    }
}
