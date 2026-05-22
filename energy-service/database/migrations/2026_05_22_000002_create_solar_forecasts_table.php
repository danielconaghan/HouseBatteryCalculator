<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_forecasts', function (Blueprint $table) {
            $table->id();
            $table->date('forecast_date')->unique();
            $table->float('estimated_kwh');
            $table->float('radiation_kwh_m2');
            $table->unsignedTinyInteger('cloud_cover_pct')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('forecast_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_forecasts');
    }
};
