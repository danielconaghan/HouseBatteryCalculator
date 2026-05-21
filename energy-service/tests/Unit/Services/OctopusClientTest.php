<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\OctopusApiException;
use App\Services\OctopusClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OctopusClientTest extends TestCase
{
    private OctopusClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new OctopusClient(
            apiKey:       'test-octopus-key',
            mpan:         '1234567890123',
            serialNumber: 'M123A',
            baseUrl:      'https://api.octopus.energy',
        );
    }

    // ─── Sad paths ────────────────────────────────────────────────────────────

    public function test_throws_when_http_request_fails(): void
    {
        Http::fake(['*' => Http::response('Unauthorized', 401)]);

        $this->expectException(OctopusApiException::class);
        $this->expectExceptionMessageMatches('/HTTP error 401/');

        $this->client->getHalfHourlyConsumption(Carbon::yesterday(), Carbon::now());
    }

    public function test_throws_on_server_error(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->expectException(OctopusApiException::class);

        $this->client->getHalfHourlyConsumption(Carbon::yesterday(), Carbon::now());
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_returns_consumption_readings_from_single_page(): void
    {
        Http::fake(['*' => Http::response($this->makePage([
            $this->makeReading(0.123, '2026-01-14T00:00:00Z', '2026-01-14T00:30:00Z'),
            $this->makeReading(0.456, '2026-01-14T00:30:00Z', '2026-01-14T01:00:00Z'),
        ]))]);

        $readings = $this->client->getHalfHourlyConsumption(
            Carbon::parse('2026-01-14'),
            Carbon::parse('2026-01-15'),
        );

        $this->assertCount(2, $readings);
        $this->assertSame(0.123, $readings[0]->consumption_kwh);
        $this->assertSame(0.456, $readings[1]->consumption_kwh);
    }

    public function test_follows_pagination_and_merges_all_pages(): void
    {
        Http::fake([
            'https://api.octopus.energy/v1/electricity-meter-points/*/consumption/*' => Http::response(
                $this->makePage([$this->makeReading(0.1)], next: 'https://api.octopus.energy/v1/page2')
            ),
            'https://api.octopus.energy/v1/page2' => Http::response(
                $this->makePage([$this->makeReading(0.2)])
            ),
        ]);

        $readings = $this->client->getHalfHourlyConsumption(
            Carbon::parse('2026-01-14'),
            Carbon::parse('2026-01-15'),
        );

        $this->assertCount(2, $readings);
        $this->assertSame(0.1, $readings[0]->consumption_kwh);
        $this->assertSame(0.2, $readings[1]->consumption_kwh);
    }

    public function test_timestamps_are_converted_to_london_timezone(): void
    {
        Http::fake(['*' => Http::response($this->makePage([
            $this->makeReading(0.1, '2026-01-14T00:00:00Z', '2026-01-14T00:30:00Z'),
        ]))]);

        $readings = $this->client->getHalfHourlyConsumption(
            Carbon::parse('2026-01-14'),
            Carbon::parse('2026-01-15'),
        );

        $this->assertSame('Europe/London', $readings[0]->interval_start->timezoneName);
    }

    public function test_uses_basic_auth_with_api_key_as_username(): void
    {
        Http::fake(['*' => Http::response($this->makePage([]))]);

        $this->client->getHalfHourlyConsumption(Carbon::yesterday(), Carbon::now());

        Http::assertSent(fn ($request) => str_contains(
            $request->header('Authorization')[0] ?? '',
            base64_encode('test-octopus-key:'),
        ));
    }

    public function test_returns_empty_array_when_no_results(): void
    {
        Http::fake(['*' => Http::response($this->makePage([]))]);

        $readings = $this->client->getHalfHourlyConsumption(Carbon::yesterday(), Carbon::now());

        $this->assertSame([], $readings);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makePage(array $results, ?string $next = null): array
    {
        return [
            'count'    => count($results),
            'next'     => $next,
            'previous' => null,
            'results'  => $results,
        ];
    }

    private function makeReading(
        float  $consumption = 0.1,
        string $start = '2026-01-14T00:00:00Z',
        string $end   = '2026-01-14T00:30:00Z',
    ): array {
        return [
            'consumption'    => $consumption,
            'interval_start' => $start,
            'interval_end'   => $end,
        ];
    }
}
