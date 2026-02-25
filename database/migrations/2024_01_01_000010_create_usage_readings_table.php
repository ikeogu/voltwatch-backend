<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * High-frequency time-series table.
 * IoT device sends readings every 10s = 8,640 rows/day/device.
 * Partition by month in production (MySQL PARTITION BY RANGE).
 * Consider migrating to InfluxDB or TimescaleDB at scale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iot_device_id')->constrained()->onDelete('cascade');
            $table->foreignId('meter_id')->constrained()->onDelete('cascade');

            // Electrical readings from hardware sensor
            $table->decimal('voltage', 6, 2)->nullable();         // V
            $table->decimal('current', 6, 3)->nullable();         // A
            $table->decimal('power_watts', 8, 2)->nullable();     // W (active power)
            $table->decimal('power_factor', 4, 3)->nullable();    // dimensionless
            $table->decimal('frequency', 5, 2)->nullable();       // Hz
            $table->decimal('energy_kwh', 10, 6)->nullable();     // Cumulative kWh from device counter

            // Derived on ingest
            $table->decimal('cost_ngn', 8, 4)->nullable();        // Estimated NGN cost of this interval
            $table->enum('supply_status', ['on', 'off'])->default('on'); // Was there power?

            $table->timestamp('recorded_at');                      // Device timestamp (not Laravel's)
            $table->timestamp('created_at')->useCurrent();         // Server ingestion time

            $table->index(['meter_id', 'recorded_at']);
            $table->index(['iot_device_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_readings');
    }
};
