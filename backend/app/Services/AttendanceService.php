<?php

namespace App\Services;

use App\Models\AttendanceLock;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Marks a set of students in one class on one date. This is a
     * first-and-only save, not a freely repeatable upsert: the register
     * (whole-class, or one subject's, per $subject) locks itself the
     * instant it's saved — a second attempt to save the SAME register is
     * refused until someone with attendance.unlock frees it again. This is
     * deliberate: attendance is meant to be a record of what actually
     * happened, not silently editable after the fact.
     *
     * With no $subject, this marks the whole-day/class register (original
     * behaviour). With a $subject, it marks a separate, subject-scoped
     * register for the same class/date — a student ends up with one
     * class-level row plus one row per subject taken that day, each
     * independently lockable without affecting the others.
     *
     * @param  array<int, array{student_id: int, status: string, remarks?: ?string}>  $entries
     *
     * @throws ValidationException if this exact register is already locked
     */
    public function markForClass(
        School $school,
        SchoolClass $class,
        Carbon $date,
        array $entries,
        User $markedBy,
        ?Subject $subject = null,
    ): void {
        if ($this->isLocked($school, $class, $date, $subject)) {
            throw ValidationException::withMessages([
                'date' => ['This register is already locked. Ask an administrator to unlock it before making changes.'],
            ]);
        }

        DB::transaction(function () use ($school, $class, $date, $entries, $markedBy, $subject) {
            foreach ($entries as $entry) {
                AttendanceRecord::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'student_id' => $entry['student_id'],
                        'date' => $date->toDateString(),
                        'subject_id' => $subject?->id,
                    ],
                    [
                        'school_class_id' => $class->id,
                        'status' => $entry['status'],
                        'remarks' => $entry['remarks'] ?? null,
                        'marked_by' => $markedBy->id,
                    ]
                );
            }

            AttendanceLock::create([
                'school_id' => $school->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject?->id,
                'date' => $date->toDateString(),
                'locked_by' => $markedBy->id,
                'locked_at' => now(),
            ]);
        });
    }

    public function isLocked(School $school, SchoolClass $class, Carbon $date, ?Subject $subject = null): bool
    {
        return $this->lockFor($school, $class, $date, $subject) !== null;
    }

    public function lockFor(School $school, SchoolClass $class, Carbon $date, ?Subject $subject = null): ?AttendanceLock
    {
        return AttendanceLock::where('school_id', $school->id)
            ->where('school_class_id', $class->id)
            ->where('subject_id', $subject?->id)
            ->where('date', $date->toDateString())
            ->first();
    }

    public function unlock(School $school, SchoolClass $class, Carbon $date, ?Subject $subject = null): void
    {
        AttendanceLock::where('school_id', $school->id)
            ->where('school_class_id', $class->id)
            ->where('subject_id', $subject?->id)
            ->where('date', $date->toDateString())
            ->delete();
    }
}
