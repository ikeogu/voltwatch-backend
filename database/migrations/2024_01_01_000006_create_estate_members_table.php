<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estate_members', function (Blueprint $table) {
           $table->ulid('id')->primary();
            $table->foreignUlid('estate_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('meter_id')->nullable()->constrained()->onDelete('set null');

            $table->string('flat_label')->nullable();      // 'Flat A', 'Unit 3', 'Shop 2'
            $table->enum('role', ['tenant', 'caretaker', 'owner'])->default('tenant');
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['estate_id', 'user_id']);
            $table->index(['estate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estate_members');
    }
};
