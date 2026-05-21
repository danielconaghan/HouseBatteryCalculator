<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\InverterStatus;
use App\Exceptions\SolaxApiException;
use App\Services\SolaxClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SolaxClientTest extends TestCase
{
    private const float TOTAL_CAPACITY_KWH = 11.6;

    private SolaxClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new SolaxClient(
            tokenId:          'test-token-id',
            wifiSn:           'TESTWIFISN',
            baseUrl:          'https://global.solaxcloud.com',
            totalCapacityKwh: self::TOTAL_CAPACITY_KWH,
        );
    }

    // ─── Sad paths ────────────────────────────────────────────────────────────

    public function test_throws_when_http_request_fails(): void
    {
        Http::fake(['*' => Http::response('Server error', 500)]);

        $this->expectException(SolaxApiException::class);
        $this->expectExceptionMessageMatches('/HTTP error 500/');

        $this->client->getBatteryState();
    }

    public function test_throws_when_api_returns_success_false(): void
    {
        Http::fake(['*' => Http::response([
            'success'   => false,
            'exception' => 'Interface Unauthorized',
            'code'      => 1001,
            'result'    => null,
        ])]);

        $this->expectException(SolaxApiException::class);
        $this->expectExceptionMessageMatches('/1001/');

        $this->client->getBatteryState();
    }

    public function test_throws_when_soc_is_null(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse(['soc' => null]))]);

        $this->expectException(SolaxApiException::class);
        $this->expectExceptionMessageMatches('/null SOC/');

        $this->client->getBatteryState();
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_returns_correct_battery_state_dto(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse([
            'soc'            => 65.0,
            'batPower'       => 1200.0,
            'inverterStatus' => '102',
        ]))]);

        $dto = $this->client->getBatteryState();

        $this->assertSame(65, $dto->charge_pct);
        $this->assertSame(round(65.0 / 100 * self::TOTAL_CAPACITY_KWH, 2), $dto->charge_kwh);
        $this->assertSame(1200.0, $dto->bat_power_w);
        $this->assertSame(InverterStatus::Normal, $dto->inverter_status);
        $this->assertSame('102', $dto->inverter_status_raw);
    }

    public function test_bat_power_is_null_when_api_returns_null(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse(['batPower' => null]))]);

        $dto = $this->client->getBatteryState();

        $this->assertNull($dto->bat_power_w);
    }

    public function test_inverter_status_is_null_for_unknown_code(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse(['inverterStatus' => '999']))]);

        $dto = $this->client->getBatteryState();

        $this->assertNull($dto->inverter_status);
        $this->assertSame('999', $dto->inverter_status_raw);
    }

    public function test_charge_kwh_is_derived_from_soc_and_total_capacity(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse(['soc' => 50.0]))]);

        $dto = $this->client->getBatteryState();

        // 50% of 11.6 kWh = 5.8 kWh
        $this->assertSame(5.8, $dto->charge_kwh);
    }

    public function test_token_id_is_sent_in_header(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse())]);

        $this->client->getBatteryState();

        Http::assertSent(fn ($request) => $request->header('tokenId')[0] === 'test-token-id');
    }

    public function test_wifi_sn_is_sent_in_request_body(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse())]);

        $this->client->getBatteryState();

        Http::assertSent(fn ($request) => $request->data()['wifiSn'] === 'TESTWIFISN');
    }

    public function test_request_is_a_post(): void
    {
        Http::fake(['*' => Http::response($this->makeResponse())]);

        $this->client->getBatteryState();

        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeResponse(array $overrides = []): array
    {
        return [
            'success'   => true,
            'exception' => 'Query success!',
            'code'      => 0,
            'result'    => array_merge([
                'inverterSN'     => 'H34TEST6008',
                'sn'             => 'TESTWIFISN',
                'acpower'        => 152,
                'yieldtoday'     => 0.2,
                'yieldtotal'     => 2420.5,
                'feedinpower'    => 0,
                'soc'            => 70.0,
                'batPower'       => 500.0,
                'batStatus'      => '0',
                'inverterStatus' => '102',
                'uploadTime'     => '2026-01-15 21:00:00',
                'powerdc1'       => 162,
                'powerdc2'       => 0,
                'utcDateTime'    => '2026-01-15T21:00:00Z',
            ], $overrides),
        ];
    }
}
