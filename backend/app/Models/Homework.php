<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Homework extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    // Eloquent's pluralizer treats "homework" as uncountable (plural ===
    // singular in English), so it would otherwise guess the table name as
    // "homework" instead of the migration's actual "homeworks".
    protected $table = 'homeworks';

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'school_class_id', 'subject_id', 'title', 'instructions',
        'due_date', 'attachment_path', 'created_by',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Homework $homework) => $homework->uuid ??= (string) Str::uuid());
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
}
