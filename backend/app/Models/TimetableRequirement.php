<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Class X has subject Y taught by teacher Z, N lessons a week" — input to the timetable generator. */
class TimetableRequirement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['school_id', 'school_class_id', 'subject_id', 'teacher_user_id', 'periods_per_week'];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }
}
