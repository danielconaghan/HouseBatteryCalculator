<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Solax\BatteryStateDTO;
use App\Enums\InverterStatus;
use App\Exceptions\SolaxApiException;
use App\Services\Contracts\SolaxClientInterface;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SolaxClient implements SolaxClientInterface
{
    private const string ENDPOINT = '/api/v2/dataAccess/realtimeInfo/get';

    public function __construct(
        private readonly string $tokenId,
        private readonly string $wifiSn,
        private readonly string $baseUrl,
        private readonly float  $totalCapacityKwh,
    ) {}

    public function getBatteryState(): BatteryStateDTO
    {
        $response = Http::withHeaders(['tokenId' => $this->tokenId])
            ->post($this->baseUrl.self::ENDPOINT, ['wifiSn' => $this->wifiSn]);

        $this->assertHttpSuccess($response);

        $body = $response->json();

        $this->assertApiSuccess($body);

        return $this->buildDto($body['result']);
    }

    private function assertHttpSuccess(Response $response): void
    {
        if (!$response->successful()) {
            throw new SolaxApiException(
                "SolaxCloud HTTP error {$response->status()}: {$response->body()}",
            );
        }
    }

    private function assertApiSuccess(array $body): void
    {
        if (!($body['success'] ?? false)) {
            $code    = $body['code'] ?? 'unknown';
            $message = $body['exception'] ?? 'no message';

            throw new SolaxApiException(
                "SolaxCloud API error (code {$code}): {$message}",
            );
        }
    }

    private function buildDto(array $result): BatteryStateDTO
    {
        if ($result['soc'] === null) {
            throw new SolaxApiException(
                'SolaxCloud returned null SOC — battery reading unavailable. '.
                'This may indicate a transient communication issue with the inverter.',
            );
        }

        $soc      = (float) $result['soc'];
        $statusRaw = (string) ($result['inverterStatus'] ?? '');

        return new BatteryStateDTO(
            charge_pct:           (int) round($soc),
            charge_kwh:           round($soc / 100.0 * $this->totalCapacityKwh, 2),
            bat_power_w:          $result['batPower'] !== null ? (float) $result['batPower'] : null,
            inverter_status:      InverterStatus::tryFrom($statusRaw),
            inverter_status_raw:  $statusRaw,
            fetched_at:           Carbon::now(),
        );
    }
}
