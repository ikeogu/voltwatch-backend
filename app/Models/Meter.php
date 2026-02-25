<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Meter extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    protected $fillable = [
        'user_id', 'tariff_band_id', 'estate_id',
        'meter_number', 'nickname', 'type', 'disco',
        'current_units', 'daily_avg_consumption',
        'units_last_updated_at', 'depletion_estimated_at',
        'tenant_count', 'is_primary', 'is_active',
    ];

    protected $casts = [
        'current_units'           => 'decimal:3',
        'daily_avg_consumption'   => 'decimal:3',
        'units_last_updated_at'   => 'datetime',
        'depletion_estimated_at'  => 'datetime',
        'is_primary'              => 'boolean',
        'is_active'               => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tariffBand()
    {
        return $this->belongsTo(TariffBand::class);
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function recharges()
    {
        return $this->hasMany(Recharge::class)->latest('recharged_at');
    }

    public function appliances()
    {
        return $this->hasMany(Appliance::class)->where('is_active', true);
    }

    public function iotDevice()
    {
        return $this->hasOne(IoTDevice::class)->latest();
    }

    public function usageReadings()
    {
        return $this->hasMany(UsageReading::class)->latest('recorded_at');
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class)->where('is_active', true);
    }

    public function dailySummaries()
    {
        return $this->hasMany(DailyUsageSummary::class)->latest('summary_date');
    }

    // ── Computed / Helpers ─────────────────────────────────────────────────

    /**
     * Estimated days until units run out based on daily average.
     */
    public function estimatedDaysRemaining(): ?float
    {
        if (!$this->daily_avg_consumption || $this->daily_avg_consumption <= 0) {
            return null;
        }
        return round($this->current_units / $this->daily_avg_consumption, 1);
    }

    /**
     * Estimated date/time of unit depletion.
     */
    public function estimatedDepletionDate(): ?Carbon
    {
        $days = $this->estimatedDaysRemaining();
        return $days ? now()->addDays($days) : null;
    }

    /**
     * Current cost per day in NGN based on tariff band.
     */
    public function dailyCostNgn(): float
    {
        return ($this->daily_avg_consumption ?? 0) * ($this->tariffBand?->rate_per_kwh ?? 0);
    }

    /**
     * Is this meter critically low? (<10 units)
     */
    public function isCriticallyLow(): bool
    {
        return $this->current_units < 10;
    }

    /**
     * Recalculate and persist daily average from last 7 days of summaries.
     */
    public function recalculateDailyAverage(): void
    {
        $avg = $this->dailySummaries()
            ->where('summary_date', '>=', now()->subDays(7)->toDateString())
            ->avg('kwh_consumed');

        $this->update([
            'daily_avg_consumption'  => $avg ?? $this->daily_avg_consumption,
            'depletion_estimated_at' => $this->estimatedDepletionDate(),
        ]);
    }

    public function getNicknameOrDefault(): string
    {
        return $this->nickname ?? 'My Meter (' . substr($this->meter_number, -4) . ')';
    }
}
