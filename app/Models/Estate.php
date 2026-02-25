<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Estate extends Model
{
    use SoftDeletes;
    protected $fillable = ['owner_id','name','address','city','state','invite_code','max_tenants','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->invite_code ??= strtoupper(Str::random(8)));
    }

    public function owner()   { return $this->belongsTo(User::class, 'owner_id'); }
    public function members() { return $this->hasMany(EstateMember::class)->where('status','active'); }
    public function meters()  { return $this->hasManyThrough(Meter::class, EstateMember::class, 'estate_id', 'id', 'id', 'meter_id'); }

    public function isFull(): bool
    {
        return $this->members()->count() >= $this->max_tenants;
    }
}
