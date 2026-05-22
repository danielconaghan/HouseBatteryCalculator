<?php

declare(strict_types=1);

use App\Jobs\FetchBatteryStateJob;
use App\Jobs\FetchConsumptionJob;
use App\Jobs\FetchSolarForecastJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(FetchBatteryStateJob::class)->everyFifteenMinutes();
Schedule::job(new FetchSolarForecastJob(totalKwp: (float) config('solar.total_kwp')))->dailyAt('06:00');
Schedule::job(FetchConsumptionJob::class)->hourly();
