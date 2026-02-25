<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone',
        'avatar_url', 'role', 'fcm_token', 'timezone',
        'notifications_enabled', 'last_active_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'     => 'datetime',
        'phone_verified_at'     => 'datetime',
        'last_active_at'        => 'datetime',
        'notifications_enabled' => 'boolean',
        'password'              => 'hashed',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function meters()
    {
        return $this->hasMany(Meter::class);
    }

    public function primaryMeter()
    {
        return $this->hasOne(Meter::class)->where('is_primary', true)->where('is_active', true);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function ownedEstates()
    {
        return $this->hasMany(Estate::class, 'owner_id');
    }

    public function estateMemberships()
    {
        return $this->hasMany(EstateMember::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function alertLogs()
    {
        return $this->hasMany(AlertLog::class)->latest();
    }

    public function recharges()
    {
        return $this->hasMany(Recharge::class)->latest();
    }

    public function iotDevices()
    {
        return $this->hasMany(IoTDevice::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isLandlord(): bool
    {
        return $this->role === 'landlord';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasActivePlan(string $plan): bool
    {
        $sub = $this->subscription;
        return $sub && $sub->status === 'active' && $sub->plan === $plan;
    }

    public function canUseIoT(): bool
    {
        $sub = $this->subscription;
        return $sub && in_array($sub->status, ['active', 'trial']) && $sub->max_iot_devices > 0;
    }

    public function getActivePlan(): string
    {
        return $this->subscription?->plan ?? 'free';
    }
}
