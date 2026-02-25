<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
     use HasUlids;

    protected $fillable = [
        'user_id','plan','status','amount_ngn',
        'paystack_subscription_code','paystack_customer_code',
        'paystack_plan_code','paystack_email_token',
        'trial_ends_at','current_period_start','current_period_end',
        'cancelled_at','max_meters','max_iot_devices',
        'has_shared_meter','has_analytics_export','has_ai_insights',
    ];

    protected $casts = [
        'trial_ends_at'          => 'datetime',
        'current_period_start'   => 'datetime',
        'current_period_end'     => 'datetime',
        'cancelled_at'           => 'datetime',
        'has_shared_meter'       => 'boolean',
        'has_analytics_export'   => 'boolean',
        'has_ai_insights'        => 'boolean',
        'amount_ngn'             => 'decimal:2',
    ];

    // Plan definitions — single source of truth for limits
    public static array $plans = [
        'free'   => ['max_meters' => 1, 'max_iot_devices' => 0, 'has_shared_meter' => false, 'has_analytics_export' => false, 'has_ai_insights' => false, 'amount_ngn' => 0],
        'basic'  => ['max_meters' => 3, 'max_iot_devices' => 0, 'has_shared_meter' => true,  'has_analytics_export' => false, 'has_ai_insights' => false, 'amount_ngn' => 500],
        'pro'    => ['max_meters' => 5, 'max_iot_devices' => 2, 'has_shared_meter' => true,  'has_analytics_export' => true,  'has_ai_insights' => true,  'amount_ngn' => 1500],
        'estate' => ['max_meters' => 20,'max_iot_devices' => 5, 'has_shared_meter' => true,  'has_analytics_export' => true,  'has_ai_insights' => true,  'amount_ngn' => 5000],
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial'])
            && (!$this->current_period_end || $this->current_period_end->isFuture());
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public static function defaultFreeFor(string  $userId): self
    {
        return self::create(array_merge(self::$plans['free'], [
            'user_id'        => $userId,
            'plan'           => 'free',
            'status'         => 'trial',
            'trial_ends_at'  => now()->addDays(7),
        ]));
    }
}
