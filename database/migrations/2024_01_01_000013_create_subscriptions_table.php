<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->enum('plan', ['free', 'basic', 'pro', 'estate'])->default('free');
            $table->enum('status', ['active', 'cancelled', 'expired', 'trial'])->default('trial');
            $table->decimal('amount_ngn', 10, 2)->nullable();

            // Paystack references
            $table->string('paystack_subscription_code')->nullable()->unique();
            $table->string('paystack_customer_code')->nullable();
            $table->string('paystack_plan_code')->nullable();
            $table->string('paystack_email_token')->nullable(); // For Paystack manage link

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Plan limits (denormalized for fast checks)
            $table->integer('max_meters')->default(1);
            $table->integer('max_iot_devices')->default(0);
            $table->boolean('has_shared_meter')->default(false);
            $table->boolean('has_analytics_export')->default(false);
            $table->boolean('has_ai_insights')->default(false);

            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
