<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table) {
           $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('tariff_band_id')->constrained();
            $table->foreignUlid('estate_id')->nullable()->constrained()->onDelete('set null');

            $table->string('meter_number')->unique();      // Physical meter number e.g. '0101234567890'
            $table->string('nickname')->nullable();        // 'Home Meter', 'Shop Meter'
            $table->enum('type', ['prepaid', 'postpaid', 'shared'])->default('prepaid');
            $table->enum('disco', [                        // Distribution company
                'ikeja', 'eko', 'aedc', 'enugu', 'phed',
                'kedco', 'ibedc', 'benin', 'jos', 'kano', 'yola'
            ])->nullable();

            // Current balance tracking
            $table->decimal('current_units', 10, 3)->default(0);  // kWh remaining
            $table->decimal('daily_avg_consumption', 8, 3)->nullable(); // Calculated rolling average kWh/day
            $table->timestamp('units_last_updated_at')->nullable();
            $table->timestamp('depletion_estimated_at')->nullable(); // Predicted run-out time

            // Shared meter config
            $table->integer('tenant_count')->default(1);  // For shared meters

            $table->boolean('is_primary')->default(true); // User's main meter
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
