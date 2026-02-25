<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ApplianceType extends Model
{
    protected $fillable = [
        'name','slug','category','icon_name',
        'avg_wattage','min_wattage','max_wattage',
        'is_always_on','is_active','sort_order'
    ];
    protected $casts = ['is_always_on' => 'boolean', 'is_active' => 'boolean'];

    public function appliances() { return $this->hasMany(Appliance::class); }
    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('sort_order'); }
}
