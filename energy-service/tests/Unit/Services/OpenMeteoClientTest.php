<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\OpenMeteoApiException;
use App\Services\OpenMeteoClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenMeteoClientTest extends TestCase
{
    private OpenMeteoClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new OpenMeteoClient(
            latitude:  51.7,
            longitude: -2.2,
        );
    }

    // ─── Sad paths ────────────────────────────────────────────────────────────

    public function test_throws_when_http_request_fails(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(OpenMeteoApiException::class);
        $this->expectExceptionMessageMatches('/HTTP error 500/');

        $this->client->getDailyForecast(Carbon::tomorrow());
    }

    public function test_throws_when_cloud_cover_is_missing_from_response(): void
    {
        Http::fake(['*' => Http::response([
            'daily' => [
                'time'                      => ['2026-01-16'],
                'shortwave_radiation_sum'   => [12.3],
                // cloud_cover_mean deliberately omitted
            ],
        ])]);

        $this->expectException(OpenMeteoApiException::class);
        $this->expectExceptionMessageMatches('/missing expected daily fields/');

        $this->client->getDailyForecast(Carbon::parse('2026-01-16'));
    }

    public function test_throws_when_radiation_is_missing_from_response(): void
    {
        Http::fake(['*' => Http::response([
            'daily' => [
                'time'             => ['2026-01-16'],
                'cloud_cover_mean' => [45],
                // shortwave_radiation_sum deliberately omitted
            ],
        ])]);

        $this->expectException(OpenMeteoApiException::class);

        $this->client->getDailyForecast(Carbon::parse('2026-01-16'));
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_returns_correct_weather_forecast_dto(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse(
            cloudCover: 45.0,
            radiation:  12.3,
        ))]);

        $dto = $this->client->getDailyForecast(Carbon::parse('2026-01-16'));

        $this->assertSame(45.0, $dto->cloud_cover_pct);
        $this->assertSame(12.3, $dto->shortwave_radiation_mj);
    }

    public function test_request_includes_correct_query_parameters(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse())]);

        $this->client->getDailyForecast(Carbon::parse('2026-01-16'));

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'latitude=51.7')
                && str_contains($url, 'longitude=-2.2')
                && str_contains($url, 'cloud_cover_mean')
                && str_contains($url, 'shortwave_radiation_sum')
                && str_contains($url, '2026-01-16');
        });
    }

    public function test_handles_zero_cloud_cover(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse(cloudCover: 0.0))]);

        $dto = $this->client->getDailyForecast(Carbon::parse('2026-01-16'));

        $this->assertSame(0.0, $dto->cloud_cover_pct);
    }

    public function test_handles_full_cloud_cover(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse(cloudCover: 100.0))]);

        $dto = $this->client->getDailyForecast(Carbon::parse('2026-01-16'));

        $this->assertSame(100.0, $dto->cloud_cover_pct);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeResponse(float $cloudCover = 30.0, float $radiation = 8.5): array
    {
        return [
            'latitude'  => 51.7,
            'longitude' => -2.2,
            'timezone'  => 'Europe/London',
            'daily'     => [
                'time'                    => ['2026-01-16'],
                'cloud_cover_mean'        => [$cloudCover],
                'shortwave_radiation_sum' => [$radiation],
            ],
        ];
    }
}
