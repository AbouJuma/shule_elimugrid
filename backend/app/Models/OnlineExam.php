<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OnlineExam extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'school_class_id', 'subject_id', 'title', 'description',
        'mode', 'time_limit_minutes', 'status', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(fn (OnlineExam $exam) => $exam->uuid ??= (string) Str::uuid());
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(OnlineExamQuestion::class)->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OnlineExamAttempt::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isGraded(): bool
    {
        return $this->mode === 'graded';
    }

    public function totalPoints(): int
    {
        return (int) $this->questions->sum('points');
    }
}
