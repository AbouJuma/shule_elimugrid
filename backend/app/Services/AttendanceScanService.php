<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * One scan, one code, either a student or a staff member — a single entry
 * point for a barcode/QR scanner (a phone camera via the app, or a
 * keyboard-wedge USB scanner via the portal) to mark whoever it just read
 * present for today. Always a single-row upsert that bypasses whichever
 * register lock might be in effect (see StaffAttendanceService::markOneViaScan
 * and the matching note here for students) — a scan is real-time gate
 * check-in, not the bulk register save the lock exists to protect.
 */
class AttendanceScanService
{
    public function __construct(protected StaffAttendanceService $staffAttendance)
    {
    }

    /**
     * @return array{type: 'student'|'staff', id: string, name: string, sub_label: ?string, status: string, already_marked: bool}
     */
    public function scan(School $school, string $rawCode, User $scannedBy): array
    {
        $code = trim($rawCode);

        if ($code === '') {
            throw ValidationException::withMessages(['code' => ['Nothing was scanned.']]);
        }

        $today = Carbon::today();

        $student = Student::where('school_id', $school->id)
            ->where(fn ($q) => $q->where('admission_number', $code)->orWhere('uuid', $code))
            ->first();

        if ($student) {
            if (! $student->school_class_id) {
                throw ValidationException::withMessages(['code' => ["{$student->fullName()} has no class assigned yet."]]);
            }

            $class = SchoolClass::findOrFail($student->school_class_id);
            $alreadyMarked = AttendanceRecord::where('student_id', $student->id)->where('date', $today->toDateString())->whereNull('subject_id')->exists();

            AttendanceRecord::updateOrCreate(
                ['school_id' => $school->id, 'student_id' => $student->id, 'date' => $today->toDateString(), 'subject_id' => null],
                ['school_class_id' => $class->id, 'status' => 'present', 'marked_by' => $scannedBy->id]
            );

            return [
                'type' => 'student',
                'id' => $student->uuid,
                'name' => $student->fullName(),
                'sub_label' => $class->name,
                'status' => 'present',
                'already_marked' => $alreadyMarked,
            ];
        }

        $staffProfile = StaffProfile::where('school_id', $school->id)->where('employee_number', $code)->first();
        $staffUser = $staffProfile?->user ?? User::where('school_id', $school->id)->where('uuid', $code)->whereHas('staffProfile')->first();

        if ($staffUser) {
            $alreadyMarked = $this->staffAttendance->isLocked($school, $today)
                || \App\Models\StaffAttendanceRecord::where('user_id', $staffUser->id)->where('date', $today->toDateString())->exists();

            $this->staffAttendance->markOneViaScan($school, $staffUser, $today, $scannedBy);

            return [
                'type' => 'staff',
                'id' => $staffUser->uuid,
                'name' => $staffUser->name,
                'sub_label' => $staffUser->staffProfile?->job_title,
                'status' => 'present',
                'already_marked' => $alreadyMarked,
            ];
        }

        throw ValidationException::withMessages(['code' => ["No student or staff member found for '{$code}'."]]);
    }
}
