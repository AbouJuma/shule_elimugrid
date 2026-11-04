<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\MarkAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AttendanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    /**
     * Classes available to take a register for. Deliberately gated on
     * `attendance.view` rather than `classes.view` — Teachers can mark
     * attendance but don't hold `classes.view` (they don't manage class
     * structure), so this mirrors what the Bootstrap portal's attendance
     * page actually does: it queries classes directly rather than going
     * through the classes.view-gated endpoint. Without this, a Teacher
     * could open the register but have no way to pick a class to open it
     * for.
     */
    public function classes()
    {
        $this->authorize('attendance.view');

        return response()->json([
            // Each class carries the subjects it offers (the class_subject
            // pivot) so the app can build the "By Subject" tabs without a
            // second round trip per class switch.
            'data' => SchoolClass::with(['subjects' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($class) => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'subjects' => $class->subjects->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                ]),
        ]);
    }

    /**
     * Returns the attendance register for a class on a date: every
     * student in that class, with their existing record for that date
     * if one was already marked (null otherwise — "not yet marked",
     * distinct from any real status).
     */
    public function index(Request $request, TenantContext $tenant, AttendanceService $attendance)
    {
        $this->authorize('attendance.view');

        $school = $tenant->assertBound();

        $request->validate([
            'school_class_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $class = SchoolClass::with(['students' => fn ($q) => $q->orderBy('first_name')])
            ->findOrFail($request->integer('school_class_id'));

        $date = Carbon::parse($request->string('date'))->toDateString();
        $subjectId = $request->integer('subject_id') ?: null;
        $subject = $subjectId ? Subject::find($subjectId) : null;
        $lock = $attendance->lockFor($school, $class, Carbon::parse($date), $subject);

        $existing = AttendanceRecord::where('school_class_id', $class->id)
            ->where('date', $date)
            ->where('subject_id', $subjectId)
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'data' => [
                'school_class' => ['id' => $class->id, 'name' => $class->name],
                'date' => $date,
                'subject_id' => $subjectId,
                'locked' => $lock !== null,
                'locked_by' => $lock?->lockedBy?->name,
                'locked_at' => $lock?->locked_at->toIso8601String(),
                'can_unlock' => $request->user()->can('attendance.unlock'),
                'students' => $class->students->map(fn ($student) => [
                    'id' => $student->id,
                    'name' => $student->fullName(),
                    'admission_number' => $student->admission_number,
                    'status' => $existing->get($student->id)?->status,
                    'remarks' => $existing->get($student->id)?->remarks,
                ]),
            ],
        ]);
    }

    public function store(MarkAttendanceRequest $request, TenantContext $tenant, AttendanceService $attendance)
    {
        $school = $tenant->assertBound();
        $data = $request->validated();

        $class = SchoolClass::where('school_id', $school->id)->findOrFail($data['school_class_id']);
        $subject = isset($data['subject_id'])
            ? Subject::where('school_id', $school->id)->find($data['subject_id'])
            : null;

        $attendance->markForClass(
            $school,
            $class,
            Carbon::parse($data['date']),
            $data['records'],
            $request->user(),
            $subject,
        );

        return response()->json(['message' => 'Attendance recorded.']);
    }

    public function unlock(Request $request, TenantContext $tenant, AttendanceService $attendance)
    {
        $this->authorize('attendance.unlock');

        $school = $tenant->assertBound();
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $class = SchoolClass::where('school_id', $school->id)->findOrFail($data['school_class_id']);
        $subject = ! empty($data['subject_id'])
            ? Subject::where('school_id', $school->id)->find($data['subject_id'])
            : null;

        $attendance->unlock($school, $class, Carbon::parse($data['date']), $subject);

        return response()->json(['message' => 'Register unlocked.']);
    }
}
