<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\ForecastSolar\SolarArrayDTO;
use App\Exceptions\ForecastSolarApiException;
use App\Services\ForecastSolarClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForecastSolarClientTest extends TestCase
{
    private ForecastSolarClient $client;
    private SolarArrayDTO       $southArray;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ForecastSolarClient(
            latitude:  51.7,
            longitude: -2.2,
        );

        $this->southArray = new SolarArrayDTO(
            name:    'Group 4 — South',
            kwp:     2.4,
            azimuth: 181,
            tilt:    35,
        );
    }

    // ─── Sad paths ────────────────────────────────────────────────────────────

    public function test_throws_when_http_request_fails(): void
    {
        Http::fake(['*' => Http::response('', 429)]);

        $this->expectException(ForecastSolarApiException::class);
        $this->expectExceptionMessageMatches('/HTTP error 429/');

        $this->client->getDailyForecast($this->southArray, Carbon::tomorrow());
    }

    public function test_throws_when_api_returns_non_zero_code(): void
    {
        Http::fake(['*' => Http::response([
            'result'  => [],
            'message' => ['code' => 429, 'text' => 'Rate limit exceeded', 'type' => 'error'],
        ])]);

        $this->expectException(ForecastSolarApiException::class);
        $this->expectExceptionMessageMatches('/Rate limit exceeded/');

        $this->client->getDailyForecast($this->southArray, Carbon::tomorrow());
    }

    public function test_throws_when_requested_date_is_not_in_response(): void
    {
        $tomorrow = Carbon::tomorrow();

        Http::fake(['*' => Http::response($this->makeResponse(
            wattHoursDay: ['1970-01-01' => 5000],
        ))]);

        $this->expectException(ForecastSolarApiException::class);
        $this->expectExceptionMessageMatches('/'.$tomorrow->toDateString().'/');

        $this->client->getDailyForecast($this->southArray, $tomorrow);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_returns_forecast_dto_with_correct_kwh(): void
    {
        $date = Carbon::parse('2026-01-16');

        Http::fake(['*' => Http::response($this->makeResponse(
            wattHoursDay: ['2026-01-16' => 8500],
        ))]);

        $dto = $this->client->getDailyForecast($this->southArray, $date);

        // 8500 Wh → 8.5 kWh
        $this->assertSame(8.5, $dto->forecast_kwh);
        $this->assertSame('Group 4 — South', $dto->array->name);
    }

    public function test_url_converts_compass_azimuth_to_south_relative(): void
    {
        $date = Carbon::parse('2026-01-16');
        Http::fake(['*' => Http::response($this->makeResponse(wattHoursDay: ['2026-01-16' => 1000]))]);

        $this->client->getDailyForecast($this->southArray, $date);

        // Compass 181° → api az = 181 - 180 = 1
        Http::assertSent(fn ($r) => str_contains($r->url(), '/35/1/'));
    }

    public function test_east_array_azimuth_converts_to_negative(): void
    {
        $eastArray = new SolarArrayDTO(name: 'East', kwp: 2.8, azimuth: 90, tilt: 35);
        $date      = Carbon::parse('2026-01-16');
        Http::fake(['*' => Http::response($this->makeResponse(wattHoursDay: ['2026-01-16' => 1000]))]);

        $this->client->getDailyForecast($eastArray, $date);

        // Compass 90° → api az = 90 - 180 = -90
        Http::assertSent(fn ($r) => str_contains($r->url(), '/35/-90/'));
    }

    public function test_period_data_is_included_in_dto(): void
    {
        $date = Carbon::parse('2026-01-16');

        Http::fake(['*' => Http::response($this->makeResponse(
            wattHoursDay:    ['2026-01-16' => 5000],
            wattHoursPeriod: ['2026-01-16 09:00:00' => 200, '2026-01-15 09:00:00' => 999],
        ))]);

        $dto = $this->client->getDailyForecast($this->southArray, $date);

        // Only periods for the requested date should be included
        $this->assertArrayHasKey('2026-01-16 09:00:00', $dto->watt_hours_by_period);
        $this->assertArrayNotHasKey('2026-01-15 09:00:00', $dto->watt_hours_by_period);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeResponse(array $wattHoursDay = [], array $wattHoursPeriod = []): array
    {
        return [
            'result'  => [
                'watt_hours_day'    => $wattHoursDay ?: ['2026-01-16' => 5000],
                'watt_hours_period' => $wattHoursPeriod,
                'watts'             => [],
            ],
            'message' => [
                'code' => 0,
                'type' => 'success',
                'text' => '',
            ],
        ];
    }
}
