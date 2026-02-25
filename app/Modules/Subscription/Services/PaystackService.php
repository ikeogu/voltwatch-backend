<?php

namespace App\Modules\Subscription\Services;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

/**
 * PaystackService
 *
 * Handles all Paystack integration for VoltWatch subscriptions.
 * Nigerian payment gateway — supports card, bank transfer, USSD.
 *
 * Paystack Dashboard: https://dashboard.paystack.com
 * Docs: https://paystack.com/docs/api
 */
class PaystackService
{
    private string $baseUrl = 'https://api.paystack.co';
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->secretKey}",
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Initialize a subscription payment.
     * Returns a Paystack authorization URL to redirect the user to.
     */
    public function initializeSubscription(User $user, string $plan): array
    {
        $planDetails = Subscription::$plans[$plan] ?? null;
        if (!$planDetails) {
            throw new \InvalidArgumentException("Unknown plan: {$plan}");
        }

        $response = Http::withHeaders($this->headers())->post("{$this->baseUrl}/transaction/initialize", [
            'email'       => $user->email,
            'amount'      => $planDetails['amount_ngn'] * 100, // Paystack uses kobo
            'plan'        => config("services.paystack.plans.{$plan}"),
            'metadata'    => [
                'user_id'  => $user->id,
                'plan'     => $plan,
                'platform' => 'voltwatch',
            ],
            'callback_url' => config('app.url') . '/api/v1/subscriptions/paystack/callback',
        ]);

        return $response->json();
    }

    /**
     * Verify a transaction by reference.
     * Called on webhook or callback.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        return $response->json();
    }

    /**
     * Cancel a subscription on Paystack.
     */
    public function cancelSubscription(string $subscriptionCode, string $emailToken): array
    {
        $response = Http::withHeaders($this->headers())->post("{$this->baseUrl}/subscription/disable", [
            'code'  => $subscriptionCode,
            'token' => $emailToken,
        ]);

        return $response->json();
    }

    /**
     * Handle Paystack webhook event.
     * Called by SubscriptionController@webhook.
     */
    public function handleWebhook(array $payload): void
    {
        $event = $payload['event'] ?? null;
        $data  = $payload['data'] ?? [];

        match ($event) {
            'charge.success'          => $this->onChargeSuccess($data),
            'subscription.create'     => $this->onSubscriptionCreate($data),
            'subscription.disable'    => $this->onSubscriptionDisable($data),
            'subscription.not_renew'  => $this->onSubscriptionNotRenew($data),
            default                   => null,
        };
    }

    private function onChargeSuccess(array $data): void
    {
        $userId = $data['metadata']['user_id'] ?? null;
        $plan   = $data['metadata']['plan'] ?? 'basic';
        if (!$userId) return;

        $user   = User::find($userId);
        $limits = Subscription::$plans[$plan];

        Subscription::updateOrCreate(
            ['user_id' => $userId],
            array_merge($limits, [
                'plan'                      => $plan,
                'status'                    => 'active',
                'amount_ngn'                => $data['amount'] / 100,
                'paystack_customer_code'    => $data['customer']['customer_code'] ?? null,
                'current_period_start'      => now(),
                'current_period_end'        => now()->addMonth(),
            ])
        );
    }

    private function onSubscriptionCreate(array $data): void
    {
        Subscription::where('paystack_customer_code', $data['customer']['customer_code'] ?? '')
            ->update([
                'paystack_subscription_code' => $data['subscription_code'] ?? null,
                'paystack_email_token'       => $data['email_token'] ?? null,
            ]);
    }

    private function onSubscriptionDisable(array $data): void
    {
        Subscription::where('paystack_subscription_code', $data['subscription_code'] ?? '')
            ->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);
    }

    private function onSubscriptionNotRenew(array $data): void
    {
        Subscription::where('paystack_subscription_code', $data['subscription_code'] ?? '')
            ->update(['status' => 'expired']);
    }

    /**
     * Verify webhook signature from Paystack.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha512', $payload, $this->secretKey);
        return hash_equals($expected, $signature);
    }
}
