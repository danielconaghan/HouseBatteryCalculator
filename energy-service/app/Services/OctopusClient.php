<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Octopus\ConsumptionReadingDTO;
use App\Exceptions\OctopusApiException;
use App\Services\Contracts\OctopusClientInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class OctopusClient implements OctopusClientInterface
{
    private const int MAX_PAGES  = 50;
    private const int PAGE_SIZE  = 100;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $mpan,
        private readonly string $serialNumber,
        private readonly string $baseUrl,
    ) {}

    /**
     * @return ConsumptionReadingDTO[]
     */
    public function getHalfHourlyConsumption(Carbon $from, Carbon $to): array
    {
        $url    = $this->consumptionUrl();
        $params = [
            'period_from' => $from->toIso8601String(),
            'period_to'   => $to->toIso8601String(),
            'page_size'   => self::PAGE_SIZE,
            'order_by'    => 'period',
        ];

        $readings = [];
        $pages    = 0;

        do {
            $body     = $this->fetchPage($url, $params);
            $readings = array_merge($readings, $this->mapReadings($body['results']));
            $url      = $body['next'] ?? null;
            $params   = [];
            $pages++;
        } while ($url !== null && $pages < self::MAX_PAGES);

        return $readings;
    }

    private function fetchPage(string $url, array $params): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')->get($url, $params);

        if (!$response->successful()) {
            throw new OctopusApiException(
                "Octopus API HTTP error {$response->status()}: {$response->body()}",
            );
        }

        return $response->json();
    }

    /**
     * @param  array<int, array<string, mixed>> $results
     * @return ConsumptionReadingDTO[]
     */
    private function mapReadings(array $results): array
    {
        return array_map(
            fn (array $r) => new ConsumptionReadingDTO(
                consumption_kwh: (float) $r['consumption'],
                interval_start:  Carbon::parse($r['interval_start'])->setTimezone('Europe/London'),
                interval_end:    Carbon::parse($r['interval_end'])->setTimezone('Europe/London'),
            ),
            $results,
        );
    }

    private function consumptionUrl(): string
    {
        return sprintf(
            '%s/v1/electricity-meter-points/%s/meters/%s/consumption/',
            rtrim($this->baseUrl, '/'),
            $this->mpan,
            $this->serialNumber,
        );
    }
}
