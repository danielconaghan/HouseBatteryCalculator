<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\CalculateChargeRecommendationAction;
use App\DTOs\ForecastSolar\SolarArrayDTO;
use App\Services\Contracts\ForecastSolarClientInterface;
use App\Services\Contracts\OctopusClientInterface;
use App\Services\Contracts\OpenMeteoClientInterface;
use App\Services\Contracts\SolaxClientInterface;
use App\Services\ForecastSolarClient;
use App\Services\OctopusClient;
use App\Services\OpenMeteoClient;
use App\Services\RecommendationInputAssembler;
use App\Services\SolaxClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindAction();
        $this->bindSolaxClient();
        $this->bindOctopusClient();
        $this->bindForecastSolarClient();
        $this->bindOpenMeteoClient();
        $this->bindRecommendationInputAssembler();
    }

    public function boot(): void
    {
        //
    }

    private function bindAction(): void
    {
        $this->app->bind(
            CalculateChargeRecommendationAction::class,
            fn () => new CalculateChargeRecommendationAction(
                batteryCeilingKwh: (float) config('battery.usable_capacity_kwh'),
                batteryCeilingPct: (int)   config('battery.charge_ceiling_pct'),
            ),
        );
    }

    private function bindSolaxClient(): void
    {
        $this->app->bind(
            SolaxClientInterface::class,
            fn () => new SolaxClient(
                tokenId:          (string) config('solax.token_id'),
                wifiSn:           (string) config('solax.wifi_sn'),
                baseUrl:          (string) config('solax.base_url'),
                totalCapacityKwh: (float)  config('battery.total_capacity_kwh'),
            ),
        );
    }

    private function bindOctopusClient(): void
    {
        $this->app->bind(
            OctopusClientInterface::class,
            fn () => new OctopusClient(
                apiKey:       (string) config('octopus.api_key'),
                mpan:         (string) config('octopus.mpan'),
                serialNumber: (string) config('octopus.serial_number'),
                baseUrl:      (string) config('octopus.base_url'),
            ),
        );
    }

    private function bindForecastSolarClient(): void
    {
        $this->app->bind(
            ForecastSolarClientInterface::class,
            fn () => new ForecastSolarClient(
                latitude:  (float) config('solar.location.latitude'),
                longitude: (float) config('solar.location.longitude'),
            ),
        );
    }

    private function bindOpenMeteoClient(): void
    {
        $this->app->bind(
            OpenMeteoClientInterface::class,
            fn () => new OpenMeteoClient(
                latitude:  (float) config('solar.location.latitude'),
                longitude: (float) config('solar.location.longitude'),
            ),
        );
    }

    private function bindRecommendationInputAssembler(): void
    {
        $this->app->bind(
            RecommendationInputAssembler::class,
            fn ($app) => new RecommendationInputAssembler(
                solax:         $app->make(SolaxClientInterface::class),
                octopus:       $app->make(OctopusClientInterface::class),
                forecastSolar: $app->make(ForecastSolarClientInterface::class),
                openMeteo:     $app->make(OpenMeteoClientInterface::class),
                solarArrays:   array_map(
                    fn (array $arr) => new SolarArrayDTO(
                        name:    (string) $arr['name'],
                        kwp:     (float)  $arr['kwp'],
                        azimuth: (int)    $arr['azimuth'],
                        tilt:    (int)    $arr['tilt'],
                    ),
                    config('solar.arrays'),
                ),
                totalKwp: (float) config('solar.total_kwp'),
            ),
        );
    }
}
