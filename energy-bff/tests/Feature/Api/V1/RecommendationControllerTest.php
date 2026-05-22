<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\DTOs\RecommendationDTO;
use App\Exceptions\EnergyServiceException;
use App\Models\User;
use App\Services\Contracts\EnergyServiceClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/recommendation')
            ->assertUnauthorized();
    }

    public function test_returns_recommendation_when_authenticated(): void
    {
        $this->mock(EnergyServiceClientInterface::class)
            ->shouldReceive('getRecommendation')
            ->once()
            ->andReturn($this->makeRecommendationDTO());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/recommendation')
            ->assertOk()
            ->assertJsonPath('data.action', 'CHARGE')
            ->assertJsonPath('data.target_charge_pct', 70)
            ->assertJsonPath('data.confidence', 0.85)
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
            ]);
    }

    public function test_returns_503_when_energy_service_unavailable(): void
    {
        $this->mock(EnergyServiceClientInterface::class)
            ->shouldReceive('getRecommendation')
            ->andThrow(new EnergyServiceException('Connection refused'));

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/recommendation')
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'ENERGY_SERVICE_UNAVAILABLE');
    }

    private function makeRecommendationDTO(): RecommendationDTO
    {
        return RecommendationDTO::from([
            'action'            => 'CHARGE',
            'target_charge_pct' => 70,
            'target_charge_kwh' => 8.1,
            'confidence'        => 0.85,
            'reasoning'         => [
                'forecast_generation_kwh'  => 5.2,
                'forecast_consumption_kwh' => 8.0,
                'current_battery_kwh'      => 3.5,
                'gap_kwh'                  => 4.5,
                'factors'                  => ['high confidence forecast'],
            ],
            'generated_at' => '2026-05-21T21:00:00+00:00',
            'valid_until'  => '2026-05-22T05:30:00+00:00',
        ]);
    }
}
