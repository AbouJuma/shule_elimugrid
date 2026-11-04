<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Guardian extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = ['school_id', 'user_id', 'name', 'phone', 'email', 'occupation', 'address', 'locale'];

    protected static function booted(): void
    {
        static::creating(function (Guardian $guardian) {
            $guardian->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }
}
