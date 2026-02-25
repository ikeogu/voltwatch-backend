<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DailyUsageSummary extends Model
{
    protected $fillable = [
        'meter_id','user_id','summary_date','kwh_consumed','cost_ngn',
        'peak_wattage','avg_wattage','min_voltage','max_voltage',
        'supply_minutes','units_recharged','recharge_count','data_source',
    ];
    protected $casts = [
        'summary_date'    => 'date',
        'kwh_consumed'    => 'decimal:4',
        'cost_ngn'        => 'decimal:2',
        'peak_wattage'    => 'decimal:2',
        'avg_wattage'     => 'decimal:2',
        'min_voltage'     => 'decimal:2',
        'max_voltage'     => 'decimal:2',
        'units_recharged' => 'decimal:3',
    ];

    public function meter() { return $this->belongsTo(Meter::class); }
    public function user()  { return $this->belongsTo(User::class); }

    public function getSupplyHoursAttribute(): float
    {
        return round(($this->supply_minutes ?? 0) / 60, 1);
    }
}
