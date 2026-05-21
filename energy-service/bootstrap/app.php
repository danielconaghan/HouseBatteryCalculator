<?php

declare(strict_types=1);

use App\Exceptions\ForecastSolarApiException;
use App\Exceptions\OctopusApiException;
use App\Exceptions\OpenMeteoApiException;
use App\Exceptions\SolaxApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $apiError = static fn (string $code, Throwable $e): array => [
            'error' => ['code' => $code, 'message' => $e->getMessage(), 'context' => []],
        ];

        $exceptions->render(fn (SolaxApiException $e)         => response()->json($apiError('INVERTER_UNAVAILABLE',    $e), 503));
        $exceptions->render(fn (OctopusApiException $e)       => response()->json($apiError('CONSUMPTION_UNAVAILABLE', $e), 503));
        $exceptions->render(fn (ForecastSolarApiException $e) => response()->json($apiError('FORECAST_UNAVAILABLE',    $e), 503));
        $exceptions->render(fn (OpenMeteoApiException $e)     => response()->json($apiError('WEATHER_UNAVAILABLE',     $e), 503));
    })->create();
