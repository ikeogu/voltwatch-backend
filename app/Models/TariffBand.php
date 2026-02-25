<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TariffBand extends Model
{
     use HasUlids;
    protected $fillable = ['code','name','rate_per_kwh','min_supply_hours','description','is_active','effective_from'];
    protected $casts = ['is_active' => 'boolean', 'effective_from' => 'datetime', 'rate_per_kwh' => 'decimal:2'];

    public function meters() { return $this->hasMany(Meter::class); }

    public function costForKwh(float $kwh): float
    {
        return round($kwh * $this->rate_per_kwh, 2);
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
