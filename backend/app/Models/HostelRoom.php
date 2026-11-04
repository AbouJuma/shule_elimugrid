<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HostelRoom extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['school_id', 'hostel_id', 'room_number', 'capacity', 'occupied_count'];

    protected static function booted(): void
    {
        static::creating(function (HostelRoom $room) {
            $room->uuid ??= (string) Str::uuid();
        });
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class);
    }

    public function isFull(): bool
    {
        return $this->occupied_count >= $this->capacity;
    }
}
