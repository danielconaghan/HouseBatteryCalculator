<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\DTOs\RecommendationInputDTO;
use App\Enums\RecommendationAction;
use App\Exceptions\ForecastSolarApiException;
use App\Exceptions\OctopusApiException;
use App\Exceptions\OpenMeteoApiException;
use App\Exceptions\SolaxApiException;
use App\Services\RecommendationInputAssembler;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-15 21:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_a_recommendation_for_tomorrow(): void
    {
        $this->mock(RecommendationInputAssembler::class)
            ->expects('assemble')
            ->with(Mockery::on(fn (Carbon $d) => $d->toDateString() === '2026-01-16'))
            ->andReturn($this->makeInput());

        $this->getJson('/api/v1/recommendation')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'action',
                    'target_charge_pct',
                    'target_charge_kwh',
                    'confidence',
                    'reasoning' => [
                        'forecast_generation_kwh',
                        'forecast_consumption_kwh',
                        'current_battery_kwh',
                        'gap_kwh',
                        'factors',
                    ],
                    'generated_at',
                    'valid_until',
                ],
            ])
            ->assertJsonPath('data.action',            RecommendationAction::DoNotCharge->value)
            ->assertJsonPath('data.target_charge_pct', 37)
            ->assertJsonPath('data.confidence',        1.0);
    }

    /** @test */
    public function it_returns_503_with_inverter_error_code_when_solax_is_unavailable(): void
    {
        $this->mock(RecommendationInputAssembler::class)
            ->expects('assemble')
            ->andThrow(new SolaxApiException('Inverter offline'));

        $this->getJson('/api/v1/recommendation')
            ->assertServiceUnavailable()
            ->assertJsonStructure(['error' => ['code', 'message', 'context']])
            ->assertJsonPath('error.code',    'INVERTER_UNAVAILABLE')
            ->assertJsonPath('error.message', 'Inverter offline');
    }

    /** @test */
    public function it_returns_503_with_consumption_error_code_when_octopus_is_unavailable(): void
    {
        $this->mock(RecommendationInputAssembler::class)
            ->expects('assemble')
            ->andThrow(new OctopusApiException('Octopus API error 429'));

        $this->getJson('/api/v1/recommendation')
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'CONSUMPTION_UNAVAILABLE');
    }

    /** @test */
    public function it_returns_503_with_forecast_error_code_when_forecast_solar_is_unavailable(): void
    {
        $this->mock(RecommendationInputAssembler::class)
            ->expects('assemble')
            ->andThrow(new ForecastSolarApiException('Rate limited'));

        $this->getJson('/api/v1/recommendation')
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'FORECAST_UNAVAILABLE');
    }

    /** @test */
    public function it_returns_503_with_weather_error_code_when_open_meteo_is_unavailable(): void
    {
        $this->mock(RecommendationInputAssembler::class)
            ->expects('assemble')
            ->andThrow(new OpenMeteoApiException('Missing daily fields'));

        $this->getJson('/api/v1/recommendation')
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'WEATHER_UNAVAILABLE');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Input that drives a DoNotCharge recommendation with full confidence:
     * gap = (8.0 - 12.0) - 3.0 = -7.0 kWh ≤ 0 → DoNotCharge
     * All confidence factors below their thresholds → confidence = 1.0
     */
    private function makeInput(): RecommendationInputDTO
    {
        return new RecommendationInputDTO(
            current_battery_kwh:              3.0,
            current_battery_pct:              37,
            forecast_generation_kwh:          12.0,
            forecast_consumption_kwh:         8.0,
            cloud_cover_pct:                  20.0,
            generation_forecast_divergence:   0.1,
            consumption_variance_coefficient: 0.05,
        );
    }
}
