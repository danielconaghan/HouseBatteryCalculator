<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Octopus\ConsumptionReadingDTO;
use App\DTOs\Solar\DailySolarForecastDTO;
use App\DTOs\Solax\BatteryStateDTO;
use App\Repositories\Contracts\BatteryReadingRepositoryInterface;
use App\Repositories\Contracts\ConsumptionReadingRepositoryInterface;
use App\Repositories\Contracts\SolarForecastRepositoryInterface;
use App\Services\RecommendationInputAssembler;
use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecommendationInputAssemblerTest extends TestCase
{
    private BatteryReadingRepositoryInterface&MockObject     $batteryRepo;
    private SolarForecastRepositoryInterface&MockObject      $forecastRepo;
    private ConsumptionReadingRepositoryInterface&MockObject $consumptionRepo;
    private RecommendationInputAssembler                     $assembler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteryRepo     = $this->createMock(BatteryReadingRepositoryInterface::class);
        $this->forecastRepo    = $this->createMock(SolarForecastRepositoryInterface::class);
        $this->consumptionRepo = $this->createMock(ConsumptionReadingRepositoryInterface::class);

        $this->assembler = new RecommendationInputAssembler(
            batteryRepo:     $this->batteryRepo,
            forecastRepo:    $this->forecastRepo,
            consumptionRepo: $this->consumptionRepo,
        );
    }

    public function test_assembles_from_fresh_repository_data(): void
    {
        $forecastDate = Carbon::tomorrow();

        $this->batteryRepo->method('latest')->willReturn($this->battery(chargeKwh: 5.0, chargePct: 43));
        $this->forecastRepo->method('forDate')->willReturn($this->forecast(estimatedKwh: 12.0, cloudCoverPct: 30));
        $this->consumptionRepo->method('getSince')->willReturn($this->consumptionWeek($forecastDate, 8.0));

        $input = $this->assembler->assemble($forecastDate);

        $this->assertEqualsWithDelta(5.0, $input->current_battery_kwh, 0.001);
        $this->assertSame(43, $input->current_battery_pct);
        $this->assertEqualsWithDelta(12.0, $input->forecast_generation_kwh, 0.001);
        $this->assertEqualsWithDelta(8.0, $input->forecast_consumption_kwh, 0.5);
        $this->assertEqualsWithDelta(30.0, $input->cloud_cover_pct, 0.001);
        $this->assertEqualsWithDelta(0.0, $input->data_staleness_factor, 0.01);
    }

    public function test_uses_zero_battery_when_no_reading_exists(): void
    {
        $this->batteryRepo->method('latest')->willReturn(null);
        $this->forecastRepo->method('forDate')->willReturn($this->forecast());
        $this->consumptionRepo->method('getSince')->willReturn([]);

        $input = $this->assembler->assemble(Carbon::tomorrow());

        $this->assertSame(0.0, $input->current_battery_kwh);
        $this->assertSame(0, $input->current_battery_pct);
    }

    public function test_uses_zero_generation_when_no_forecast_exists(): void
    {
        $this->batteryRepo->method('latest')->willReturn($this->battery());
        $this->forecastRepo->method('forDate')->willReturn(null);
        $this->consumptionRepo->method('getSince')->willReturn([]);

        $input = $this->assembler->assemble(Carbon::tomorrow());

        $this->assertSame(0.0, $input->forecast_generation_kwh);
    }

    public function test_uses_default_consumption_when_no_history_exists(): void
    {
        $this->batteryRepo->method('latest')->willReturn($this->battery());
        $this->forecastRepo->method('forDate')->willReturn($this->forecast());
        $this->consumptionRepo->method('getSince')->willReturn([]);

        $input = $this->assembler->assemble(Carbon::tomorrow());

        $this->assertEqualsWithDelta(10.0, $input->forecast_consumption_kwh, 0.001);
    }

    public function test_staleness_factor_increases_for_stale_battery(): void
    {
        $staleAt = Carbon::now()->subMinutes(45);
        $this->batteryRepo->method('latest')->willReturn($this->battery(fetchedAt: $staleAt));
        $this->forecastRepo->method('forDate')->willReturn($this->forecast());
        $this->consumptionRepo->method('getSince')->willReturn([]);

        $input = $this->assembler->assemble(Carbon::tomorrow());

        $this->assertGreaterThan(0.0, $input->data_staleness_factor);
    }

    public function test_staleness_factor_zero_when_all_data_fresh(): void
    {
        $forecastDate = Carbon::tomorrow();
        $this->batteryRepo->method('latest')->willReturn($this->battery());
        $this->forecastRepo->method('forDate')->willReturn($this->forecast());
        $this->consumptionRepo->method('getSince')->willReturn($this->consumptionWeek($forecastDate, 8.0));

        $input = $this->assembler->assemble($forecastDate);

        $this->assertEqualsWithDelta(0.0, $input->data_staleness_factor, 0.01);
    }

    public function test_staleness_maxes_at_one_when_all_sources_missing(): void
    {
        $this->batteryRepo->method('latest')->willReturn(null);
        $this->forecastRepo->method('forDate')->willReturn(null);
        $this->consumptionRepo->method('getSince')->willReturn([]);

        $input = $this->assembler->assemble(Carbon::tomorrow());

        $this->assertEqualsWithDelta(1.0, $input->data_staleness_factor, 0.01);
    }

    public function test_cloud_cover_defaults_to_100_when_no_forecast(): void
    {
        $this->batteryRepo->method('latest')->willReturn($this->battery());
        $this->forecastRepo->method('forDate')->willReturn(null);
        $this->consumptionRepo->method('getSince')->willReturn([]);

        $input = $this->assembler->assemble(Carbon::tomorrow());

        $this->assertSame(100.0, $input->cloud_cover_pct);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function battery(
        float   $chargeKwh = 4.0,
        int     $chargePct = 35,
        ?Carbon $fetchedAt = null,
    ): BatteryStateDTO {
        return new BatteryStateDTO(
            charge_pct:          $chargePct,
            charge_kwh:          $chargeKwh,
            bat_power_w:         null,
            inverter_status:     null,
            inverter_status_raw: '102',
            fetched_at:          $fetchedAt ?? Carbon::now(),
        );
    }

    private function forecast(
        float $estimatedKwh  = 10.0,
        ?int  $cloudCoverPct = 20,
    ): DailySolarForecastDTO {
        return new DailySolarForecastDTO(
            forecast_date:    Carbon::tomorrow(),
            estimated_kwh:    $estimatedKwh,
            radiation_kwh_m2: 3.5,
            cloud_cover_pct:  $cloudCoverPct,
            generated_at:     Carbon::now(),
        );
    }

    /**
     * Generates one historical day's worth of half-hourly readings matching
     * $forecastDate's day-of-week, plus a recent reading to mark the job as
     * having run within the staleness window.
     *
     * @return ConsumptionReadingDTO[]
     */
    private function consumptionWeek(Carbon $forecastDate, float $dailyKwh): array
    {
        $readings   = [];
        $halfHourly = $dailyKwh / 48;
        $targetDow  = $forecastDate->dayOfWeek;

        $day = $forecastDate->copy()->subWeek()->startOfDay();
        while ($day->dayOfWeek !== $targetDow) {
            $day->addDay();
        }

        for ($i = 0; $i < 48; $i++) {
            $start      = $day->copy()->addMinutes($i * 30);
            $readings[] = new ConsumptionReadingDTO(
                consumption_kwh: $halfHourly,
                interval_start:  $start,
                interval_end:    $start->copy()->addMinutes(30),
            );
        }

        // A recent reading confirms the job ran within the staleness window
        $recentStart = Carbon::now()->subMinutes(90);
        $readings[]  = new ConsumptionReadingDTO(
            consumption_kwh: $halfHourly,
            interval_start:  $recentStart,
            interval_end:    $recentStart->copy()->addMinutes(30),
        );

        return $readings;
    }
}
