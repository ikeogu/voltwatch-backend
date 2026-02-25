<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated daily summaries.
 * Built nightly by GenerateWeeklyReport job.
 * Makes analytics queries instant — no scanning usage_readings table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_usage_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->date('summary_date');
            $table->decimal('kwh_consumed', 10, 4)->default(0);
            $table->decimal('cost_ngn', 10, 2)->default(0);
            $table->decimal('peak_wattage', 8, 2)->nullable();         // Highest wattage seen that day
            $table->decimal('avg_wattage', 8, 2)->nullable();
            $table->decimal('min_voltage', 6, 2)->nullable();          // Useful for IoT quality monitoring
            $table->decimal('max_voltage', 6, 2)->nullable();
            $table->integer('supply_minutes')->nullable();              // Minutes power was actually on
            $table->decimal('units_recharged', 10, 3)->default(0);     // kWh bought that day
            $table->integer('recharge_count')->default(0);
            $table->enum('data_source', ['iot', 'estimated'])->default('estimated');

            $table->timestamps();

            $table->unique(['meter_id', 'summary_date']);
            $table->index(['user_id', 'summary_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_usage_summaries');
    }
};
