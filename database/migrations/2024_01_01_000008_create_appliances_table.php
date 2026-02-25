<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appliances', function (Blueprint $table) {
           $table->ulid('id')->primary();
            $table->foreignUlid('meter_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('appliance_type_id')->constrained();

            $table->string('nickname')->nullable();        // 'Living Room AC', 'Kitchen Fridge'
            $table->integer('quantity')->default(1);       // How many of this appliance
            $table->integer('wattage')->nullable();        // Override default if user knows exact wattage
            $table->decimal('daily_hours', 5, 2);          // Hours used per day (user estimate)

            // Calculated fields (updated by ConsumptionEstimator job)
            $table->decimal('daily_kwh', 8, 3)->nullable();    // quantity * wattage * daily_hours / 1000
            $table->decimal('daily_cost_ngn', 8, 2)->nullable(); // daily_kwh * tariff rate
            $table->decimal('monthly_kwh', 8, 3)->nullable();
            $table->decimal('monthly_cost_ngn', 8, 2)->nullable();
            $table->decimal('usage_percentage', 5, 2)->nullable(); // % of total meter consumption

            $table->boolean('is_always_on')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['meter_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appliances');
    }
};
