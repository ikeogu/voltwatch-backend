<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IoTDevice extends Model
{
    use SoftDeletes, HasUlids;

    protected $table = 'iot_devices';
    
    protected $fillable = [
        'meter_id','user_id','device_id','nickname','firmware_version',
        'connection_type','status','last_voltage','last_current',
        'last_power_watts','last_power_factor','last_frequency',
        'last_seen_at','reading_interval_seconds','mqtt_topic','config',
    ];

    protected $casts = [
        'last_voltage'      => 'decimal:2',
        'last_current'      => 'decimal:3',
        'last_power_watts'  => 'decimal:2',
        'last_power_factor' => 'decimal:3',
        'last_frequency'    => 'decimal:2',
        'last_seen_at'      => 'datetime',
        'config'            => 'array',
    ];

    public function meter()         { return $this->belongsTo(Meter::class); }
    public function user()          { return $this->belongsTo(User::class); }
    public function usageReadings() { return $this->hasMany(UsageReading::class)->latest('recorded_at'); }

    public function isOnline(): bool
    {
        return $this->status === 'online'
            && $this->last_seen_at
            && $this->last_seen_at->diffInSeconds(now()) < ($this->reading_interval_seconds * 3);
    }

    public function getMqttTopicAttribute($value): string
    {
        return $value ?? "voltwatch/device/{$this->device_id}";
    }

    /** Snapshot the latest reading fields */
    public function updateFromReading(array $data): void
    {
        $this->update([
            'last_voltage'      => $data['voltage'] ?? null,
            'last_current'      => $data['current'] ?? null,
            'last_power_watts'  => $data['power_watts'] ?? null,
            'last_power_factor' => $data['power_factor'] ?? null,
            'last_frequency'    => $data['frequency'] ?? null,
            'last_seen_at'      => now(),
            'status'            => 'online',
        ]);
    }
}
