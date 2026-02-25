<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appliance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');                    // 'cooling','kitchen','lighting','entertainment','other'
            $table->string('icon_name');                   // React Native icon key
            $table->integer('avg_wattage');                // Average watts e.g. 746 for 1hp AC
            $table->integer('min_wattage')->nullable();
            $table->integer('max_wattage')->nullable();
            $table->boolean('is_always_on')->default(false); // fridge, modem etc
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appliance_types');
    }
};
