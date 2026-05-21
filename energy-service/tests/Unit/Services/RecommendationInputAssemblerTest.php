<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\ForecastSolar\SolarArrayDTO;
use App\DTOs\ForecastSolar\SolarForecastDTO;
use App\DTOs\Octopus\ConsumptionReadingDTO;
use App\DTOs\OpenMeteo\WeatherForecastDTO;
use App\DTOs\Solax\BatteryStateDTO;
use App\Exceptions\OctopusApiException;
use App\Services\Contracts\ForecastSolarClientInterface;
use App\Services\Contracts\OctopusClientInterface;
use App\Services\Contracts\OpenMeteoClientInterface;
use App\Services\Contracts\SolaxClientInterface;
use App\Services\RecommendationInputAssembler;
use Carbon\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RecommendationInputAssemblerTest extends TestCase
{
    private SolaxClientInterface&MockInterface         $solax;
    private OctopusClientInterface&MockInterface       $octopus;
    private ForecastSolarClientInterface&MockInterface $forecastSolar;
    private OpenMeteoClientInterface&MockInterface     $openMeteo;

    private SolarArrayDTO $array1;
    private SolarArrayDTO $array2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-01-15 21:00:00'); // Thursday

        $this->solax         = Mockery::mock(SolaxClientInterface::class);
        $this->octopus       = Mockery::mock(OctopusClientInterface::class);
        $this->forecastSolar = Mockery::mock(ForecastSolarClientInterface::class);
        $this->openMeteo     = Mockery::mock(OpenMeteoClientInterface::class);

        $this->array1 = new SolarArrayDTO(name: 'South', kwp: 2.0, azimuth: 181, tilt: 35);
        $this->array2 = new SolarArrayDTO(name: 'East',  kwp: 2.0, azimuth: 90,  tilt: 35);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_assembles_a_recommendation_input_dto_from_all_clients(): void
    {
        $date = Carbon::parse('2026-01-15');

        $this->solax->expects('getBatteryState')->andReturn(
            $this->makeBatteryState(charge_kwh: 3.0, charge_pct: 37)
        );

        $this->forecastSolar->expects('getDailyForecast')
            ->with($this->array1, Mockery::type(Carbon::class))
            ->andReturn($this->makeSolarForecast($this->array1, 5.0));

        $this->forecastSolar->expects('getDailyForecast')
            ->with($this->array2, Mockery::type(Carbon::class))
            ->andReturn($this->makeSolarForecast($this->array2, 7.0));

        $this->openMeteo->expects('getDailyForecast')
            ->andReturn($this->makeWeatherForecast($date, cloud_cover: 30.0, radiation: 20.0));

        $this->octopus->expects('getHalfHourlyConsumption')
            ->andReturn($this->makeThursdayReadings());

        $result = $this->makeAssembler()->assemble($date);

        $this->assertEqualsWithDelta(3.0,  $result->current_battery_kwh,     0.001);
        $this->assertSame(37,              $result->current_battery_pct);
        $this->assertEqualsWithDelta(12.0, $result->forecast_generation_kwh,  0.001); // 5 + 7
        $this->assertEqualsWithDelta(11.0, $result->forecast_consumption_kwh, 0.001); // mean(10,12,11)
        $this->assertEqualsWithDelta(30.0, $result->cloud_cover_pct,          0.001);
    }

    /** @test */
    public function it_sums_generation_across_all_arrays(): void
    {
        $date = Carbon::parse('2026-01-15');

        $this->stubSolax();
        $this->stubOctopus();
        $this->stubOpenMeteo($date);

        $this->forecastSolar->expects('getDailyForecast')
            ->with($this->array1, Mockery::any())
            ->andReturn($this->makeSolarForecast($this->array1, 3.5));

        $this->forecastSolar->expects('getDailyForecast')
            ->with($this->array2, Mockery::any())
            ->andReturn($this->makeSolarForecast($this->array2, 4.5));

        $result = $this->makeAssembler()->assemble($date);

        $this->assertEqualsWithDelta(8.0, $result->forecast_generation_kwh, 0.001);
    }

    /** @test */
    public function it_filters_consumption_to_matching_day_of_week_only(): void
    {
        $date = Carbon::parse('2026-01-15'); // Thursday

        $this->stubSolax();
        $this->stubForecastSolar($date);
        $this->stubOpenMeteo($date);

        // Mix of Thursdays and Wednesdays — Wednesdays must be excluded from mean
        $readings = array_merge(
            $this->makeReadingsForDate('2026-01-08', 10.0), // Thursday ✓
            $this->makeReadingsForDate('2026-01-07', 20.0), // Wednesday ✗
            $this->makeReadingsForDate('2026-01-01', 12.0), // Thursday ✓
            $this->makeReadingsForDate('2025-12-31', 30.0), // Wednesday ✗
        );

        $this->octopus->expects('getHalfHourlyConsumption')->andReturn($readings);

        $result = $this->makeAssembler()->assemble($date);

        // mean([10.0, 12.0]) = 11.0 — Wednesday readings excluded
        $this->assertEqualsWithDelta(11.0, $result->forecast_consumption_kwh, 0.001);
    }

    /** @test */
    public function it_computes_sample_variance_coefficient_from_same_day_totals(): void
    {
        $date = Carbon::parse('2026-01-15'); // Thursday

        $this->stubSolax();
        $this->stubForecastSolar($date);
        $this->stubOpenMeteo($date);

        // [10, 12, 11] → mean=11, sample std dev=1.0, CV = 1/11 ≈ 0.0909
        $this->octopus->expects('getHalfHourlyConsumption')
            ->andReturn($this->makeThursdayReadings());

        $result = $this->makeAssembler()->assemble($date);

        $this->assertEqualsWithDelta(round(1.0 / 11.0, 4), $result->consumption_variance_coefficient, 0.0001);
    }

    /** @test */
    public function variance_coefficient_is_zero_for_a_single_data_point(): void
    {
        $date = Carbon::parse('2026-01-15'); // Thursday

        $this->stubSolax();
        $this->stubForecastSolar($date);
        $this->stubOpenMeteo($date);

        $this->octopus->expects('getHalfHourlyConsumption')
            ->andReturn($this->makeReadingsForDate('2026-01-08', 10.0)); // one Thursday only

        $result = $this->makeAssembler()->assemble($date);

        $this->assertEqualsWithDelta(0.0, $result->consumption_variance_coefficient, 0.0001);
    }

    /** @test */
    public function it_computes_generation_divergence_against_open_meteo_implied_kwh(): void
    {
        $date        = Carbon::parse('2026-01-15');
        $totalKwp    = 4.0;
        $radiationMj = 20.0;
        $forecastKwh = 5.0;

        $this->stubSolax();
        $this->stubOctopus();

        $this->forecastSolar->allows('getDailyForecast')
            ->andReturn($this->makeSolarForecast($this->array1, $forecastKwh));

        $this->openMeteo->expects('getDailyForecast')
            ->andReturn($this->makeWeatherForecast($date, cloud_cover: 0.0, radiation: $radiationMj));

        $result = $this->makeAssembler(totalKwp: $totalKwp, arrays: [$this->array1])->assemble($date);

        $impliedKwh = $radiationMj * 0.2778 * $totalKwp * 0.75;
        $expected   = round(abs($forecastKwh - $impliedKwh) / max($forecastKwh, $impliedKwh), 4);

        $this->assertEqualsWithDelta($expected, $result->generation_forecast_divergence, 0.0001);
    }

    /** @test */
    public function divergence_is_zero_when_both_sources_produce_zero(): void
    {
        $date = Carbon::parse('2026-01-15');

        $this->stubSolax();
        $this->stubOctopus();

        $this->forecastSolar->allows('getDailyForecast')
            ->andReturn($this->makeSolarForecast($this->array1, 0.0));

        $this->openMeteo->expects('getDailyForecast')
            ->andReturn($this->makeWeatherForecast($date, cloud_cover: 0.0, radiation: 0.0));

        $result = $this->makeAssembler(totalKwp: 4.0, arrays: [$this->array1])->assemble($date);

        $this->assertEqualsWithDelta(0.0, $result->generation_forecast_divergence, 0.0001);
    }

    /** @test */
    public function it_throws_octopus_exception_when_no_consumption_data_matches_day_of_week(): void
    {
        $date = Carbon::parse('2026-01-15'); // Thursday

        $this->stubSolax();
        $this->stubForecastSolar($date);
        $this->stubOpenMeteo($date);

        // Only Wednesday readings — assembler must throw because no Thursdays exist
        $this->octopus->expects('getHalfHourlyConsumption')
            ->andReturn($this->makeReadingsForDate('2026-01-07', 10.0));

        $this->expectException(OctopusApiException::class);

        $this->makeAssembler()->assemble($date);
    }

    /** @test */
    public function it_passes_the_correct_date_range_to_octopus(): void
    {
        $date = Carbon::parse('2026-01-15');

        $this->stubSolax();
        $this->stubForecastSolar($date);
        $this->stubOpenMeteo($date);

        $this->octopus->expects('getHalfHourlyConsumption')
            ->withArgs(function (Carbon $from, Carbon $to): bool {
                // 6 weeks before 2026-01-15 = 2025-12-04, start of day
                // day before 2026-01-15     = 2026-01-14, end of day
                return $from->toDateString() === '2025-12-04'
                    && $to->toDateString()   === '2026-01-14';
            })
            ->andReturn($this->makeThursdayReadings());

        $this->makeAssembler()->assemble($date);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAssembler(float $totalKwp = 4.0, ?array $arrays = null): RecommendationInputAssembler
    {
        return new RecommendationInputAssembler(
            solax:         $this->solax,
            octopus:       $this->octopus,
            forecastSolar: $this->forecastSolar,
            openMeteo:     $this->openMeteo,
            solarArrays:   $arrays ?? [$this->array1, $this->array2],
            totalKwp:      $totalKwp,
        );
    }

    private function makeBatteryState(float $charge_kwh = 3.0, int $charge_pct = 37): BatteryStateDTO
    {
        return new BatteryStateDTO(
            charge_pct:          $charge_pct,
            charge_kwh:          $charge_kwh,
            bat_power_w:         null,
            inverter_status:     null,
            inverter_status_raw: '',
            fetched_at:          Carbon::now(),
        );
    }

    private function makeSolarForecast(SolarArrayDTO $array, float $kwh): SolarForecastDTO
    {
        return new SolarForecastDTO(
            array:                $array,
            forecast_kwh:         $kwh,
            date:                 Carbon::parse('2026-01-15')->startOfDay(),
            watt_hours_by_period: [],
        );
    }

    private function makeWeatherForecast(Carbon $date, float $cloud_cover, float $radiation): WeatherForecastDTO
    {
        return new WeatherForecastDTO(
            cloud_cover_pct:        $cloud_cover,
            shortwave_radiation_mj: $radiation,
            date:                   $date->startOfDay()->clone(),
        );
    }

    /**
     * Three Thursdays within the 6-week window: 10 kWh, 12 kWh, 11 kWh.
     * Mean = 11.0; sample std dev = 1.0; CV = 1/11 ≈ 0.0909.
     *
     * @return ConsumptionReadingDTO[]
     */
    private function makeThursdayReadings(): array
    {
        return array_merge(
            $this->makeReadingsForDate('2026-01-08', 10.0),
            $this->makeReadingsForDate('2026-01-01', 12.0),
            $this->makeReadingsForDate('2025-12-25', 11.0),
        );
    }

    /**
     * Single half-hour reading representing the full day's consumption.
     * This is sufficient because aggregateDailyConsumption sums by date key.
     *
     * @return ConsumptionReadingDTO[]
     */
    private function makeReadingsForDate(string $date, float $totalKwh): array
    {
        $start = Carbon::parse($date)->startOfDay()->setTimezone('Europe/London');

        return [
            new ConsumptionReadingDTO(
                consumption_kwh: $totalKwh,
                interval_start:  $start,
                interval_end:    $start->copy()->addDay(),
            ),
        ];
    }

    // -------------------------------------------------------------------------
    // Stubs (lenient allows — use when the test cares about other things)
    // -------------------------------------------------------------------------

    private function stubSolax(): void
    {
        $this->solax->allows('getBatteryState')->andReturn($this->makeBatteryState());
    }

    private function stubOctopus(): void
    {
        $this->octopus->allows('getHalfHourlyConsumption')->andReturn($this->makeThursdayReadings());
    }

    private function stubForecastSolar(Carbon $date): void
    {
        $this->forecastSolar->allows('getDailyForecast')
            ->andReturn($this->makeSolarForecast($this->array1, 5.0));
    }

    private function stubOpenMeteo(Carbon $date): void
    {
        $this->openMeteo->allows('getDailyForecast')
            ->andReturn($this->makeWeatherForecast($date, cloud_cover: 30.0, radiation: 20.0));
    }
}
