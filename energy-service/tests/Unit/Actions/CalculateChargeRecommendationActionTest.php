<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CalculateChargeRecommendationAction;
use App\DTOs\RecommendationInputDTO;
use App\Enums\RecommendationAction;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CalculateChargeRecommendationActionTest extends TestCase
{
    private const float BATTERY_CEILING_KWH = 8.1;
    private const int   BATTERY_CEILING_PCT = 70;

    private CalculateChargeRecommendationAction $action;

    protected function setUp(): void
    {
        Carbon::setTestNow('2026-01-15 21:00:00');

        $this->action = new CalculateChargeRecommendationAction(
            batteryCeilingKwh: self::BATTERY_CEILING_KWH,
            batteryCeilingPct: self::BATTERY_CEILING_PCT,
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    // ─── Sad paths first ─────────────────────────────────────────────────────

    public function test_recommends_full_charge_when_gap_exceeds_threshold(): void
    {
        // consumption=15, generation=5, battery=2 → gap = (15-5)-2 = 8 → ≥ 25% of 8.1 kWh
        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: 15.0,
            forecast_generation_kwh: 5.0,
            current_battery_kwh: 2.0,
            current_battery_pct: 17,
        ));

        $this->assertSame(RecommendationAction::Charge, $result->action);
        $this->assertSame(self::BATTERY_CEILING_PCT, $result->target_charge_pct);
        $this->assertSame(self::BATTERY_CEILING_KWH, $result->target_charge_kwh);
    }

    public function test_confidence_is_degraded_by_high_cloud_cover(): void
    {
        $result = $this->action->execute($this->makeInput(cloud_cover_pct: 85.0));

        $this->assertSame(0.8, $result->confidence);
    }

    public function test_confidence_is_degraded_by_high_forecast_divergence(): void
    {
        $result = $this->action->execute($this->makeInput(generation_forecast_divergence: 0.5));

        $this->assertSame(0.8, $result->confidence);
    }

    public function test_confidence_is_degraded_by_high_consumption_variance(): void
    {
        $result = $this->action->execute($this->makeInput(consumption_variance_coefficient: 0.6));

        $this->assertSame(0.85, $result->confidence);
    }

    public function test_confidence_is_degraded_by_all_three_factors(): void
    {
        $result = $this->action->execute($this->makeInput(
            cloud_cover_pct: 90.0,
            generation_forecast_divergence: 0.5,
            consumption_variance_coefficient: 0.6,
        ));

        // 1.0 - 0.2 (cloud) - 0.2 (divergence) - 0.15 (variance) = 0.45
        $this->assertSame(0.45, $result->confidence);
    }

    public function test_confidence_is_not_degraded_below_zero(): void
    {
        // Construct a scenario that would push confidence below zero if unclamped.
        // Override constants cannot be changed, so use maximum penalties across all factors.
        $result = $this->action->execute($this->makeInput(
            cloud_cover_pct: 100.0,
            generation_forecast_divergence: 1.0,
            consumption_variance_coefficient: 1.0,
        ));

        $this->assertGreaterThanOrEqual(0.0, $result->confidence);
    }

    // ─── Decision logic ──────────────────────────────────────────────────────

    public function test_recommends_partial_charge_when_gap_is_below_threshold(): void
    {
        // consumption=12, generation=8, battery=3 → gap = (12-8)-3 = 1.0 → < 8.1*0.25=2.025
        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: 12.0,
            forecast_generation_kwh: 8.0,
            current_battery_kwh: 3.0,
            current_battery_pct: 26,
        ));

        $this->assertSame(RecommendationAction::PartialCharge, $result->action);
    }

    public function test_recommends_partial_charge_just_below_threshold(): void
    {
        // gap = 8.1 * 0.25 - 0.01 = 2.015 → PARTIAL_CHARGE
        $gap      = (self::BATTERY_CEILING_KWH * 0.25) - 0.01;
        $battery  = 2.0;
        $need     = $gap + $battery;

        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: $need,
            forecast_generation_kwh: 0.0,
            current_battery_kwh: $battery,
            current_battery_pct: 17,
        ));

        $this->assertSame(RecommendationAction::PartialCharge, $result->action);
    }

    public function test_does_not_recommend_charge_when_solar_and_battery_cover_consumption(): void
    {
        // consumption=10, generation=20, battery=4 → gap = (10-20)-4 = -14 → DO_NOT_CHARGE
        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: 10.0,
            forecast_generation_kwh: 20.0,
            current_battery_kwh: 4.0,
            current_battery_pct: 35,
        ));

        $this->assertSame(RecommendationAction::DoNotCharge, $result->action);
    }

    public function test_does_not_charge_when_gap_is_exactly_zero(): void
    {
        // battery exactly covers the need after solar
        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: 10.0,
            forecast_generation_kwh: 6.0,
            current_battery_kwh: 4.0,
            current_battery_pct: 35,
        ));

        $this->assertSame(RecommendationAction::DoNotCharge, $result->action);
    }

    // ─── Target calculation ───────────────────────────────────────────────────

    public function test_charge_target_is_battery_ceiling(): void
    {
        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: 15.0,
            forecast_generation_kwh: 2.0,
            current_battery_kwh: 1.0,
            current_battery_pct: 9,
        ));

        $this->assertSame(self::BATTERY_CEILING_PCT, $result->target_charge_pct);
        $this->assertSame(self::BATTERY_CEILING_KWH, $result->target_charge_kwh);
    }

    public function test_partial_charge_target_is_current_plus_gap(): void
    {
        // gap = (12-8)-3 = 1.0, so target = 3.0+1.0 = 4.0 kWh
        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: 12.0,
            forecast_generation_kwh: 8.0,
            current_battery_kwh: 3.0,
            current_battery_pct: 26,
        ));

        $this->assertSame(4.0, $result->target_charge_kwh);
        $this->assertLessThanOrEqual(self::BATTERY_CEILING_PCT, $result->target_charge_pct);
    }

    public function test_do_not_charge_target_is_current_battery_state(): void
    {
        $result = $this->action->execute($this->makeInput(
            forecast_consumption_kwh: 10.0,
            forecast_generation_kwh: 20.0,
            current_battery_kwh: 4.0,
            current_battery_pct: 35,
        ));

        $this->assertSame(35, $result->target_charge_pct);
        $this->assertSame(4.0, $result->target_charge_kwh);
    }

    // ─── Reasoning and factors ────────────────────────────────────────────────

    public function test_reasoning_always_contains_generation_consumption_summary(): void
    {
        $result = $this->action->execute($this->makeInput());

        $this->assertNotEmpty($result->reasoning->factors);
        $this->assertStringContainsString('generation', $result->reasoning->factors[0]);
        $this->assertStringContainsString('consumption', $result->reasoning->factors[0]);
    }

    public function test_reasoning_includes_cloud_cover_factor_when_threshold_exceeded(): void
    {
        $result = $this->action->execute($this->makeInput(cloud_cover_pct: 75.0));

        $factors = implode(' ', $result->reasoning->factors);
        $this->assertStringContainsString('Cloud cover', $factors);
    }

    public function test_reasoning_does_not_include_cloud_factor_below_threshold(): void
    {
        $result = $this->action->execute($this->makeInput(cloud_cover_pct: 59.9));

        $factors = implode(' ', $result->reasoning->factors);
        $this->assertStringNotContainsString('Cloud cover', $factors);
    }

    public function test_reasoning_includes_divergence_factor_when_threshold_exceeded(): void
    {
        $result = $this->action->execute($this->makeInput(generation_forecast_divergence: 0.4));

        $factors = implode(' ', $result->reasoning->factors);
        $this->assertStringContainsString('diverge', $factors);
    }

    public function test_reasoning_includes_variance_factor_when_threshold_exceeded(): void
    {
        $result = $this->action->execute($this->makeInput(consumption_variance_coefficient: 0.5));

        $factors = implode(' ', $result->reasoning->factors);
        $this->assertStringContainsString('variance', $factors);
    }

    // ─── Validity window ─────────────────────────────────────────────────────

    public function test_valid_until_is_eight_hours_after_generated_at(): void
    {
        $result = $this->action->execute($this->makeInput());

        $this->assertTrue($result->valid_until->isAfter($result->generated_at));
        $this->assertSame(8, $result->generated_at->diffInHours($result->valid_until));
    }

    public function test_reasoning_reflects_input_values(): void
    {
        $result = $this->action->execute($this->makeInput(
            forecast_generation_kwh: 12.5,
            forecast_consumption_kwh: 9.3,
            current_battery_kwh: 3.7,
        ));

        $this->assertSame(12.5, $result->reasoning->forecast_generation_kwh);
        $this->assertSame(9.3, $result->reasoning->forecast_consumption_kwh);
        $this->assertSame(3.7, $result->reasoning->current_battery_kwh);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_confidence_is_full_when_all_conditions_are_ideal(): void
    {
        $result = $this->action->execute($this->makeInput(
            cloud_cover_pct: 10.0,
            generation_forecast_divergence: 0.05,
            consumption_variance_coefficient: 0.1,
        ));

        $this->assertSame(1.0, $result->confidence);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeInput(
        float $current_battery_kwh = 4.0,
        int   $current_battery_pct = 35,
        float $forecast_generation_kwh = 20.0,
        float $forecast_consumption_kwh = 10.0,
        float $cloud_cover_pct = 20.0,
        float $generation_forecast_divergence = 0.1,
        float $consumption_variance_coefficient = 0.2,
    ): RecommendationInputDTO {
        return new RecommendationInputDTO(
            current_battery_kwh: $current_battery_kwh,
            current_battery_pct: $current_battery_pct,
            forecast_generation_kwh: $forecast_generation_kwh,
            forecast_consumption_kwh: $forecast_consumption_kwh,
            cloud_cover_pct: $cloud_cover_pct,
            generation_forecast_divergence: $generation_forecast_divergence,
            consumption_variance_coefficient: $consumption_variance_coefficient,
        );
    }
}
