<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UsageReading extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'iot_device_id','meter_id','voltage','current','power_watts',
        'power_factor','frequency','energy_kwh','cost_ngn','supply_status','recorded_at',
    ];
    protected $casts = [
        'voltage'       => 'decimal:2',
        'current'       => 'decimal:3',
        'power_watts'   => 'decimal:2',
        'power_factor'  => 'decimal:3',
        'frequency'     => 'decimal:2',
        'energy_kwh'    => 'decimal:6',
        'cost_ngn'      => 'decimal:4',
        'recorded_at'   => 'datetime',
    ];

    public function iotDevice() { return $this->belongsTo(IoTDevice::class); }
    public function meter()     { return $this->belongsTo(Meter::class); }
}
