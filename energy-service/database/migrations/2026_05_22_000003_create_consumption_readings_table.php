<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_readings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('interval_start')->unique();
            $table->timestamp('interval_end');
            $table->float('consumption_kwh');
            $table->timestamp('created_at')->useCurrent();

            $table->index('interval_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_readings');
    }
};
