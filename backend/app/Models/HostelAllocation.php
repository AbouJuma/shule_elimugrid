<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HostelAllocation extends Model
{
    use BelongsToTenant, HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'hostel_room_id', 'student_id', 'allocated_date',
        'vacated_date', 'status', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated_date' => 'date',
            'vacated_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HostelAllocation $allocation) {
            $allocation->uuid ??= (string) Str::uuid();
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'hostel_room_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
