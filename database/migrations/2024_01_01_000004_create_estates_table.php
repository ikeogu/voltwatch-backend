<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade'); // landlord
            $table->string('name');                        // '14 Adeola Close'
            $table->string('address');
            $table->string('city')->default('Lagos');
            $table->string('state')->default('Lagos');
            $table->string('invite_code', 10)->unique();  // Short code for tenants to join
            $table->integer('max_tenants')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estates');
    }
};
