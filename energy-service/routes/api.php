<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\RecommendationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — energy-service
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1/.
| Controllers validate via Form Requests; responses via API Resources.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/recommendation', RecommendationController::class);
});
