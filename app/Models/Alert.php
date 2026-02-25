<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'user_id','meter_id','type','threshold_value','threshold_days',
        'severity','notify_push','notify_sms','is_active','cooldown_minutes','last_fired_at',
    ];
    protected $casts = [
        'notify_push'       => 'boolean',
        'notify_sms'        => 'boolean',
        'is_active'         => 'boolean',
        'last_fired_at'     => 'datetime',
        'threshold_value'   => 'decimal:3',
    ];

    public function user()  { return $this->belongsTo(User::class); }
    public function meter() { return $this->belongsTo(Meter::class); }
    public function logs()  { return $this->hasMany(AlertLog::class)->latest(); }

    public function isCoolingDown(): bool
    {
        return $this->last_fired_at
            && $this->last_fired_at->diffInMinutes(now()) < $this->cooldown_minutes;
    }

    public function markFired(): void
    {
        $this->update(['last_fired_at' => now()]);
    }
}
