<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Hostel extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['school_id', 'name', 'gender', 'warden_user_id'];

    protected static function booted(): void
    {
        static::creating(function (Hostel $hostel) {
            $hostel->uuid ??= (string) Str::uuid();
        });
    }

    public function warden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warden_user_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HostelRoom::class);
    }
}
