<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->decimal('units_added', 10, 3);         // kWh credited
            $table->decimal('amount_paid', 10, 2);         // NGN paid
            $table->decimal('rate_at_time', 8, 2);         // NGN/kWh at time of purchase (snapshot)
            $table->decimal('units_before', 10, 3)->default(0); // Balance before this recharge
            $table->decimal('units_after', 10, 3);         // Balance after

            $table->string('token_number')->nullable();    // The actual NERC token (masked after save)
            $table->enum('source', ['manual', 'iot_scan', 'api'])->default('manual');
            $table->text('notes')->nullable();
            $table->date('recharged_at');                  // User-reported date (can backdate)
            $table->timestamps();

            $table->index(['meter_id', 'recharged_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharges');
    }
};
