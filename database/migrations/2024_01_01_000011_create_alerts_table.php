<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
           $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('meter_id')->constrained()->onDelete('cascade');

            $table->enum('type', [
                'low_units',            // "Alert me when units below X"
                'high_daily_usage',     // "Alert me when daily burn exceeds X kWh"
                'estimated_depletion',  // "Alert me X days before running out"
                'voltage_drop',         // IoT: "Alert when voltage drops below 180V"
                'power_surge',          // IoT: "Alert when wattage exceeds X W"
                'recharge_reminder',    // "Remind me every X days"
                'unusual_usage',        // AI-detected anomaly
            ]);

            // Threshold values (flexible — not all types use all fields)
            $table->decimal('threshold_value', 10, 3)->nullable();  // e.g., 20 for "below 20 units"
            $table->integer('threshold_days')->nullable();            // For depletion warnings
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');

            // Delivery channels
            $table->boolean('notify_push')->default(true);
            $table->boolean('notify_sms')->default(false);
            $table->boolean('is_active')->default(true);

            // Rate limiting — don't spam user
            $table->integer('cooldown_minutes')->default(60);        // Min gap between same alert fires
            $table->timestamp('last_fired_at')->nullable();

            $table->timestamps();
            $table->index(['meter_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
