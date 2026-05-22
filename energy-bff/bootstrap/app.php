<?php

use App\Exceptions\EnergyServiceException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum SPA authentication middleware will be configured here.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (EnergyServiceException $e, Request $request) {
            Log::error('Energy service unavailable', [
                'message'     => $e->getMessage(),
                'status_code' => $e->getCode(),
                'path'        => $request->path(),
            ]);

            return response()->json([
                'error' => [
                    'code'    => 'ENERGY_SERVICE_UNAVAILABLE',
                    'message' => 'The energy service is currently unavailable.',
                    'context' => [],
                ],
            ], 503);
        });
    })->create();
