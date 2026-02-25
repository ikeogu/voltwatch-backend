<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_devices', function (Blueprint $table) {
           $table->ulid('id')->primary();
            $table->foreignUlid('meter_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');

            $table->string('device_id')->unique();          // Hardware serial e.g. 'VW-2024-ABC123'
            $table->string('nickname')->nullable();          // 'Main Meter Monitor'
            $table->string('firmware_version')->nullable();  // For OTA updates
            $table->enum('connection_type', ['wifi', 'gsm', 'ethernet'])->default('wifi');
            $table->enum('status', ['online', 'offline', 'pairing', 'error'])->default('pairing');

            // Last known readings (snapshot from latest MQTT message)
            $table->decimal('last_voltage', 6, 2)->nullable();    // Volts e.g. 224.50
            $table->decimal('last_current', 6, 3)->nullable();    // Amps e.g. 6.842
            $table->decimal('last_power_watts', 8, 2)->nullable(); // Watts e.g. 1528.60
            $table->decimal('last_power_factor', 4, 3)->nullable(); // 0.000 - 1.000
            $table->decimal('last_frequency', 5, 2)->nullable();   // Hz e.g. 49.98
            $table->timestamp('last_seen_at')->nullable();

            // Config
            $table->integer('reading_interval_seconds')->default(10); // How often device reports
            $table->string('mqtt_topic')->nullable();       // e.g. 'voltwatch/device/VW-2024-ABC123'
            $table->json('config')->nullable();             // Flexible device config JSON

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_devices');
    }
};
