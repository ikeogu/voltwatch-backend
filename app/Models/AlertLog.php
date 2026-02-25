<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AlertLog extends Model
{
    protected $fillable = [
        'alert_id','user_id','meter_id','title','message',
        'trigger_value','severity','status','channels_used','read_at',
    ];
    protected $casts = [
        'trigger_value'  => 'decimal:3',
        'channels_used'  => 'array',
        'read_at'        => 'datetime',
    ];

    public function alert()  { return $this->belongsTo(Alert::class); }
    public function user()   { return $this->belongsTo(User::class); }
    public function meter()  { return $this->belongsTo(Meter::class); }

    public function markRead(): void
    {
        if (!$this->read_at) {
            $this->update(['status' => 'read', 'read_at' => now()]);
        }
    }
}
