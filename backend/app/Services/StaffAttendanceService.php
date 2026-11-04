<?php

namespace App\Services;

use App\Models\School;
use App\Models\StaffAttendanceLock;
use App\Models\StaffAttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff/teacher attendance — deliberately parallel to AttendanceService
 * (students), not a shared abstraction: the two have different "who" (a
 * User vs a Student), different scope (whole school vs one class/subject),
 * and evolving them together would only make each harder to read for a
 * marginal reduction in duplication.
 */
class StaffAttendanceService
{
    /**
     * Bulk register save — the whole staff list for one date, submitted
     * together. Locks itself on save, same rule as student attendance
     * (Phase 34): a second save of the same date is refused until
     * someone with staff_attendance.unlock frees it again.
     *
     * @param  array<int, array{user_id: int, status: string, remarks?: ?string}>  $entries
     *
     * @throws ValidationException if today's register is already locked
     */
    public function markForSchool(School $school, Carbon $date, array $entries, User $markedBy): void
    {
        if ($this->isLocked($school, $date)) {
            throw ValidationException::withMessages([
                'date' => ['This register is already locked. Ask an administrator to unlock it before making changes.'],
            ]);
        }

        DB::transaction(function () use ($school, $date, $entries, $markedBy) {
            foreach ($entries as $entry) {
                StaffAttendanceRecord::updateOrCreate(
                    ['school_id' => $school->id, 'user_id' => $entry['user_id'], 'date' => $date->toDateString()],
                    ['status' => $entry['status'], 'remarks' => $entry['remarks'] ?? null, 'source' => 'register', 'marked_by' => $markedBy->id]
                );
            }

            StaffAttendanceLock::create([
                'school_id' => $school->id,
                'date' => $date->toDateString(),
                'locked_by' => $markedBy->id,
                'locked_at' => now(),
            ]);
        });
    }

    /**
     * A single gate scan marking one staff member present. Deliberately
     * bypasses the register lock: scanning is a continuous, real-time
     * check-in process throughout the morning, not the one-time bulk save
     * the lock exists to protect — the two can coexist for the same date.
     */
    public function markOneViaScan(School $school, User $staff, Carbon $date, User $scannedBy): StaffAttendanceRecord
    {
        return StaffAttendanceRecord::updateOrCreate(
            ['school_id' => $school->id, 'user_id' => $staff->id, 'date' => $date->toDateString()],
            ['status' => 'present', 'source' => 'scan', 'marked_by' => $scannedBy->id]
        );
    }

    public function isLocked(School $school, Carbon $date): bool
    {
        return $this->lockFor($school, $date) !== null;
    }

    public function lockFor(School $school, Carbon $date): ?StaffAttendanceLock
    {
        return StaffAttendanceLock::where('school_id', $school->id)->where('date', $date->toDateString())->first();
    }

    public function unlock(School $school, Carbon $date): void
    {
        StaffAttendanceLock::where('school_id', $school->id)->where('date', $date->toDateString())->delete();
    }
}
