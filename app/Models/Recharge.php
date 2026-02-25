<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Recharge extends Model
{
     use HasUlids;
    protected $fillable = [
        'meter_id','user_id','units_added','amount_paid','rate_at_time',
        'units_before','units_after','token_number','source','notes','recharged_at',
    ];

    protected $casts = [
        'units_added'   => 'decimal:3',
        'amount_paid'   => 'decimal:2',
        'rate_at_time'  => 'decimal:2',
        'units_before'  => 'decimal:3',
        'units_after'   => 'decimal:3',
        'recharged_at'  => 'date',
    ];

    // Never expose full token — only last 4 digits
    protected $hidden = ['token_number'];

    public function meter() { return $this->belongsTo(Meter::class); }
    public function user()  { return $this->belongsTo(User::class); }

    public function getMaskedTokenAttribute(): ?string
    {
        return $this->token_number
            ? '****-****-****-' . substr(str_replace(['-',' '], '', $this->token_number), -4)
            : null;
    }

    public function costPerUnit(): float
    {
        return $this->units_added > 0 ? round($this->amount_paid / $this->units_added, 2) : 0;
    }
}
