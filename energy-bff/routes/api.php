<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — energy-bff
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1/.
| This BFF aggregates responses from energy-service for the UI.
| No business logic here — aggregation and auth only.
|
*/

Route::prefix('v1')->group(function () {
    // Auth routes (Sanctum) will be registered here.
    // Aggregated resource routes will be registered here.
    // e.g. Route::get('/dashboard', DashboardController::class)->middleware('auth:sanctum');
});
