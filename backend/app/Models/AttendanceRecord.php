<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'school_class_id', 'subject_id', 'date', 'status', 'remarks', 'marked_by',
    ];

    // Deliberately NOT cast to 'date': Eloquent's 'date' cast reformats
    // the value on save differently from the plain Y-m-d string used to
    // build updateOrCreate()'s WHERE clause in AttendanceService, which
    // broke the upsert (search and stored value stopped matching, so
    // resubmitting the same register inserted a duplicate instead of
    // updating — caught by test_resubmitting_the_same_register_updates_
    // rather_than_duplicates). Keeping it a plain Y-m-d string keeps
    // both sides byte-identical.

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    // Null for a whole-day/class register row; set for a per-subject one.
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
