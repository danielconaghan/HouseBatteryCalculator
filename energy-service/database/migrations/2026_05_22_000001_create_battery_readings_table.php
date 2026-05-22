<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('charge_pct');
            $table->float('charge_kwh');
            $table->float('bat_power_w')->nullable();
            $table->string('inverter_status')->nullable();
            $table->string('inverter_status_raw');
            $table->timestamp('fetched_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_readings');
    }
};
