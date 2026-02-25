<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Appliance extends Model
{
     use HasUlids;
     
    protected $fillable = [
        'meter_id','user_id','appliance_type_id','nickname','quantity',
        'wattage','daily_hours','daily_kwh','daily_cost_ngn',
        'monthly_kwh','monthly_cost_ngn','usage_percentage',
        'is_always_on','is_active',
    ];

    protected $casts = [
        'daily_hours'        => 'decimal:2',
        'daily_kwh'          => 'decimal:3',
        'daily_cost_ngn'     => 'decimal:2',
        'monthly_kwh'        => 'decimal:3',
        'monthly_cost_ngn'   => 'decimal:2',
        'usage_percentage'   => 'decimal:2',
        'is_always_on'       => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function meter()         { return $this->belongsTo(Meter::class); }
    public function user()          { return $this->belongsTo(User::class); }
    public function applianceType() { return $this->belongsTo(ApplianceType::class); }

    /** Effective wattage: user override OR catalogue default */
    public function effectiveWattage(): int
    {
        return $this->wattage ?? $this->applianceType?->avg_wattage ?? 0;
    }

    /** Compute daily kWh for this appliance */
    public function computeDailyKwh(): float
    {
        $hours = $this->is_always_on ? 24 : $this->daily_hours;
        return round(($this->effectiveWattage() * $this->quantity * $hours) / 1000, 4);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?? $this->applianceType?->name ?? 'Appliance';
    }
}
