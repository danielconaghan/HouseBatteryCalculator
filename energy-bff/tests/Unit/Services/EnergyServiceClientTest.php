<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\RecommendationDTO;
use App\Enums\RecommendationAction;
use App\Exceptions\EnergyServiceException;
use App\Services\EnergyServiceClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnergyServiceClientTest extends TestCase
{
    public function test_throws_on_connection_failure(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        $this->expectException(EnergyServiceException::class);
        $this->expectExceptionMessageMatches('/Could not connect/');

        $this->makeClient()->getRecommendation();
    }

    public function test_throws_on_503_from_energy_service(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->expectException(EnergyServiceException::class);

        $this->makeClient()->getRecommendation();
    }

    public function test_throws_on_500_from_energy_service(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(EnergyServiceException::class);

        $this->makeClient()->getRecommendation();
    }

    public function test_returns_recommendation_dto_on_success(): void
    {
        Http::fake(['*' => Http::response($this->successPayload(), 200)]);

        $dto = $this->makeClient()->getRecommendation();

        $this->assertInstanceOf(RecommendationDTO::class, $dto);
        $this->assertSame(RecommendationAction::Charge, $dto->action);
        $this->assertSame(70, $dto->target_charge_pct);
        $this->assertSame(8.1, $dto->target_charge_kwh);
        $this->assertSame(0.85, $dto->confidence);
    }

    public function test_maps_reasoning_fields_correctly(): void
    {
        Http::fake(['*' => Http::response($this->successPayload(), 200)]);

        $dto = $this->makeClient()->getRecommendation();

        $this->assertSame(5.2, $dto->reasoning->forecast_generation_kwh);
        $this->assertSame(8.0, $dto->reasoning->forecast_consumption_kwh);
        $this->assertSame(3.5, $dto->reasoning->current_battery_kwh);
        $this->assertSame(4.5, $dto->reasoning->gap_kwh);
        $this->assertSame(['high confidence forecast'], $dto->reasoning->factors);
    }

    private function makeClient(): EnergyServiceClient
    {
        return new EnergyServiceClient(
            Http::baseUrl('http://energy-service')->acceptJson()->timeout(10),
        );
    }

    private function successPayload(): array
    {
        return [
            'data' => [
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
            ],
        ];
    }
}
