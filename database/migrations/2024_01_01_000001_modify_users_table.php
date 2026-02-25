<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Modifies the default Laravel users table for VoltWatch
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->unique()->after('email')->nullable();
            $table->string('phone_verified_at')->nullable()->after('phone');
            $table->string('avatar_url')->nullable()->after('phone_verified_at');
            $table->enum('role', ['user', 'landlord', 'admin'])->default('user')->after('avatar_url');
            $table->string('fcm_token')->nullable()->after('role'); // Firebase push notification token
            $table->string('timezone')->default('Africa/Lagos')->after('fcm_token');
            $table->boolean('notifications_enabled')->default(true)->after('timezone');
            $table->timestamp('last_active_at')->nullable()->after('notifications_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'phone_verified_at', 'avatar_url', 'role',
                'fcm_token', 'timezone', 'notifications_enabled', 'last_active_at'
            ]);
        });
    }
};
