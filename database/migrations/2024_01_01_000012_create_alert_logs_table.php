<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_logs', function (Blueprint $table) {
           $table->ulid('id')->primary();
            $table->foreignUlid('alert_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('meter_id')->constrained()->onDelete('cascade');

            $table->string('title');
            $table->text('message');
            $table->decimal('trigger_value', 10, 3)->nullable(); // The value that triggered alert
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->enum('status', ['sent', 'read', 'dismissed'])->default('sent');
            $table->json('channels_used')->nullable();           // ['push', 'sms']
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
    }
};
