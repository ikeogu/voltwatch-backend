<?php

namespace App\Modules\Subscription\Controllers;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Modules\Subscription\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends ApiController
{
    public function __construct(private PaystackService $paystack) {}

    /** GET /api/v1/subscription */
    public function show(Request $request): JsonResponse
    {
        $sub  = $request->user()->subscription;
        $plans = \App\Models\Subscription::$plans;

        return $this->successResponse("Fetched subscription details",[
            'current' => $sub ? [
                'plan'              => $sub->plan,
                'status'            => $sub->status,
                'is_active'         => $sub->isActive(),
                'is_on_trial'       => $sub->isOnTrial(),
                'trial_ends_at'     => $sub->trial_ends_at?->toIso8601String(),
                'renews_at'         => $sub->current_period_end?->toIso8601String(),
                'amount_ngn'        => $sub->amount_ngn,
                'max_meters'        => $sub->max_meters,
                'max_iot_devices'   => $sub->max_iot_devices,
                'has_shared_meter'  => $sub->has_shared_meter,
                'has_ai_insights'   => $sub->has_ai_insights,
            ] : null,
            'plans' => collect($plans)->map(fn($p, $key) => array_merge($p, ['plan' => $key])),
        ]);
    }

    /** POST /api/v1/subscription/checkout */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate(['plan' => 'required|in:basic,pro,estate']);

        $result = $this->paystack->initializeSubscription($request->user(), $data['plan']);

        if (!($result['status'] ?? false)) {
            return response()->json(['message' => 'Payment initialization failed. Please try again.'], 422);
        }

        return response()->json([
            'checkout_url' => $result['data']['authorization_url'],
            'reference'    => $result['data']['reference'],
        ]);
    }

    /** POST /api/v1/subscription/cancel */
    public function cancel(Request $request): JsonResponse
    {
        $sub = $request->user()->subscription;
        if (!$sub || !$sub->paystack_subscription_code) {
            return response()->json(['message' => 'No active subscription found.'], 422);
        }

        $result = $this->paystack->cancelSubscription(
            $sub->paystack_subscription_code,
            $sub->paystack_email_token
        );

        if ($result['status'] ?? false) {
            $sub->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            return response()->json(['message' => 'Subscription cancelled. Access continues until end of billing period.']);
        }

        return response()->json(['message' => 'Cancellation failed. Please contact support.'], 422);
    }

    /**
     * POST /api/v1/subscriptions/paystack/webhook
     * Paystack signs every webhook with HMAC-SHA512
     */
    public function webhook(Request $request): JsonResponse
    {
        $signature = $request->header('x-paystack-signature');
        $payload   = $request->getContent();

        if (!$this->paystack->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Paystack webhook signature');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $this->paystack->handleWebhook($request->all());

        return response()->json(['ok' => true]);
    }
}
