<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nigeria NERC Tariff Bands (2024 rates)
 * Band A: ≥20hrs supply → ₦225/kWh
 * Band B: ≥16hrs supply → ₦63.34/kWh
 * Band C: ≥12hrs supply → ₦50.27/kWh
 * Band D: ≥8hrs supply  → ₦44.97/kWh
 * Band E: <8hrs supply  → ₦36.94/kWh
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_bands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();         // 'A', 'B', 'C', 'D', 'E'
            $table->string('name');                        // 'Band A', 'Band B', etc.
            $table->decimal('rate_per_kwh', 8, 2);        // NGN per kWh (e.g., 225.00)
            $table->integer('min_supply_hours');           // Minimum hours of supply per day
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable(); // When this rate became effective
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_bands');
    }
};
