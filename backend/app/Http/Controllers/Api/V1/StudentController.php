<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\StoreStudentRequest;
use App\Http\Requests\Admissions\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentStatusChange;
use Illuminate\Support\Facades\DB;
use App\Services\AdmissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('students.view');

        $students = Student::query()
            ->with(['schoolClass', 'stream', 'academicYear'])
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('last_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('admission_number', 'like', '%'.$request->string('search').'%')
            ))
            ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->integer('stream_id'), fn ($q, $id) => $q->where('stream_id', $id))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('admission_date')
            ->paginate($request->integer('per_page', 20));

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request, TenantContext $tenant, AdmissionService $admissions)
    {
        /** @var School $school */
        $school = $tenant->assertBound();

        $student = $admissions->admit($school, $request->validated());
        $notification = $request->boolean('notify_guardian', true) ? $this->notifyGuardian($student, $request->user()) : ['status' => 'off'];

        return response()->json(['data' => new StudentResource($student), 'notification' => $notification], 201);
    }

    /** Invoices the new student and texts their parent what to pay; a failure here never undoes the admission. */
    protected function notifyGuardian(\App\Models\Student $student, ?\App\Models\User $by): array
    {
        try {
            $result = app(\App\Services\FeeMessageService::class)->onAdmission($student, $by);

            return ['status' => $result['status'], 'reason' => $result['reason'], 'invoice_number' => $result['invoice']?->invoice_number, 'control_number' => $result['invoice']?->control_number];
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'skipped', 'reason' => 'error', 'invoice_number' => null, 'control_number' => null];
        }
    }


    public function show(Student $student)
    {
        $this->authorize('students.view');

        return new StudentResource($student->load(['schoolClass', 'stream', 'academicYear', 'guardians']));
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $data = $request->validated();

        if (array_key_exists('status', $data) && $data['status'] !== 'active') {
            if (! $request->user()->can('students.archive')) {
                throw ValidationException::withMessages([
                    'status' => ['You do not have permission to change this student\'s status.'],
                ]);
            }
        } elseif (! $request->user()->can('students.update')) {
            throw ValidationException::withMessages([
                'status' => ['You do not have permission to update this student.'],
            ]);
        }

        $reason = $data['reason'] ?? null;
        unset($data['reason']);
        $statusChanged = array_key_exists('status', $data) && $data['status'] !== $student->status;

        // Same rule as the portal: a status change must say why, and is logged.
        if ($statusChanged && ! $reason) {
            throw ValidationException::withMessages(['reason' => [__('validation.required', ['attribute' => __('reason')])]]);
        }

        DB::transaction(function () use ($student, $data, $statusChanged, $reason, $request) {
            if ($statusChanged) {
                StudentStatusChange::create([
                    'school_id' => $student->school_id, 'student_id' => $student->id,
                    'from_status' => $student->status, 'to_status' => $data['status'],
                    'reason' => $reason, 'changed_by' => $request->user()->id,
                ]);
            }

            $student->update($data);
        });

        return new StudentResource($student->fresh(['schoolClass', 'stream', 'academicYear', 'guardians']));
    }
}
