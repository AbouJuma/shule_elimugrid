<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Academics\MarkAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AttendanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant, AttendanceService $attendance)
    {
        $this->authorize('attendance.view');

        $school = $tenant->assertBound();
        $classes = SchoolClass::query()->orderBy('name')->get();

        $selectedClassId = $request->integer('school_class_id') ?: null;
        $selectedSubjectId = $request->integer('subject_id') ?: null;
        $date = $request->string('date')->isNotEmpty() ? $request->string('date')->toString() : now()->toDateString();

        $register = null;
        $subjects = collect();
        $lock = null;

        if ($selectedClassId) {
            $class = $classes->firstWhere('id', $selectedClassId);

            if ($class) {
                $class->load([
                    'students' => fn ($q) => $q->orderBy('first_name'),
                    'subjects' => fn ($q) => $q->orderBy('name'),
                ]);
                $subjects = $class->subjects;

                // A subject tab picked for a class that no longer offers it
                // (e.g. removed after the link was bookmarked) quietly falls
                // back to the whole-class register rather than erroring.
                if ($selectedSubjectId && ! $subjects->contains('id', $selectedSubjectId)) {
                    $selectedSubjectId = null;
                }

                $subject = $selectedSubjectId ? $subjects->firstWhere('id', $selectedSubjectId) : null;
                $lock = $attendance->lockFor($school, $class, Carbon::parse($date), $subject);

                $existing = AttendanceRecord::where('school_class_id', $class->id)
                    ->where('date', $date)
                    ->where('subject_id', $selectedSubjectId)
                    ->get()
                    ->keyBy('student_id');

                $register = [
                    'class' => $class,
                    'students' => $class->students->map(fn ($student) => [
                        'student' => $student,
                        'status' => $existing->get($student->id)?->status,
                        'remarks' => $existing->get($student->id)?->remarks,
                    ]),
                ];
            }
        }

        return view('school-admin.attendance.index', [
            'classes' => $classes,
            'subjects' => $subjects,
            'selectedClassId' => $selectedClassId,
            'selectedSubjectId' => $selectedSubjectId,
            'date' => $date,
            'register' => $register,
            'lock' => $lock,
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

        $attendance->markForClass($school, $class, Carbon::parse($data['date']), $data['records'], $request->user(), $subject);

        return redirect()
            ->route('school-admin.attendance.index', [
                'school_class_id' => $class->id,
                'date' => $data['date'],
                'subject_id' => $subject?->id,
            ])
            ->with('status', 'Attendance saved.');
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

        return redirect()
            ->route('school-admin.attendance.index', [
                'school_class_id' => $class->id,
                'date' => $data['date'],
                'subject_id' => $subject?->id,
            ])
            ->with('status', 'Register unlocked — you can now make changes.');
    }
}
