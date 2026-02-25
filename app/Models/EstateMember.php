<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class EstateMember extends Model
{
     use HasUlids;
    protected $fillable = ['estate_id','user_id','meter_id','flat_label','role','status','joined_at'];
    protected $casts = ['joined_at' => 'datetime'];

    public function estate() { return $this->belongsTo(Estate::class); }
    public function user()   { return $this->belongsTo(User::class); }
    public function meter()  { return $this->belongsTo(Meter::class); }

    public function scopeActive($q) { return $q->where('status','active'); }
}
