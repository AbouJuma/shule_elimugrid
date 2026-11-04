<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Admissions\StoreStudentRequest;
use App\Http\Requests\Admissions\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentStatusChange;
use Illuminate\Support\Facades\DB;
use App\Services\AdmissionService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('students.view');

        $students = Student::query()
            ->with(['schoolClass', 'stream', 'academicYear', 'guardians.user', 'statusChanges.changedBy'])
            ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->integer('stream_id'), fn ($q, $id) => $q->where('stream_id', $id))
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('last_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('admission_number', 'like', '%'.$request->string('search').'%')
            ))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('admission_date')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.students.index', [
            'students' => $students,
            // Needed to render the "Admit Student" modal on this same
            // page, even before it's opened.
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->with('streams')->orderBy('name')->get(),
            'guardians' => Guardian::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStudentRequest $request, TenantContext $tenant, AdmissionService $admissions)
    {
        $student = $admissions->admit($tenant->assertBound(), $request->validated());
        $notification = $request->boolean('notify_guardian', true) ? $this->notifyGuardian($student, $request->user()) : ['status' => 'off'];

        $message = __(':p1 admitted — admission number :p2.', ['p1' => $student->fullName(), 'p2' => $student->admission_number]);

        if ($notification['status'] === 'sent') {
            $message .= ' '.__('The parent was sent the fees to pay (control number :cn).', ['cn' => $notification['control_number']]);
        } elseif ($notification['status'] === 'skipped') {
            $message .= ' '.__(match ($notification['reason']) {
                'no_fee_structure' => 'No fee structure exists for this class and year, so no fees message was sent.',
                'no_guardian_phone' => 'The parent has no phone number, so no fees message was sent.',
                'no_class' => 'The student has no class yet, so no fees message was sent.',
                default => 'The fees message could not be sent.',
            });
        }

        return redirect()
            ->route('school-admin.students.index')
            ->with('status', $message);
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


    public function update(UpdateStudentRequest $request, Student $student)
    {
        $data = $request->validated();

        if (array_key_exists('status', $data) && $data['status'] !== 'active' && ! $request->user()->can('students.archive')) {
            throw ValidationException::withMessages([
                'status' => ['You do not have permission to change this student\'s status.'],
            ]);
        }

        $student->update($data);

        return redirect()->route('school-admin.students.index')->with('status', __(':p1 updated.', ['p1' => $student->fullName()]));
    }

    public function archive(Request $request, Student $student)
    {
        $this->authorize('students.archive');

        $data = $request->validate([
            'status' => 'required|in:graduated,withdrawn,transferred,suspended,active',
            'reason' => 'required|string|min:3|max:500',
        ]);

        if ($data['status'] === $student->status) {
            return back()->withErrors(['status' => 'The student already has that status.']);
        }

        DB::transaction(function () use ($student, $data, $request) {
            StudentStatusChange::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'from_status' => $student->status,
                'to_status' => $data['status'],
                'reason' => $data['reason'],
                'changed_by' => $request->user()->id,
            ]);

            $student->update(['status' => $data['status']]);
        });

        return redirect()->route('school-admin.students.index')
            ->with('status', __(':p1 marked :p2.', ['p1' => $student->fullName(), 'p2' => __($student->status)]));
    }

    /**
     * A soft delete — for a record that shouldn't have existed at all (a
     * duplicate, a mis-admitted test entry), unlike archive()'s status
     * change, which keeps the student fully on record. The row itself is
     * never removed (SoftDeletes), so it can still be recovered later if
     * that turns out to have been a mistake.
     */
    public function destroy(Student $student)
    {
        $this->authorize('students.delete');

        $name = $student->fullName();
        $student->delete();

        return redirect()->route('school-admin.students.index')->with('status', __(':p1 deleted.', ['p1' => $name]));
    }

    /**
     * A small QR encoding the student's admission number — printable or
     * screenable for the gate scanner (AttendanceScanService looks a
     * scanned code up by admission_number first).
     */
    public function qr(Student $student)
    {
        $this->authorize('students.view');

        $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($student->admission_number);

        return response($svg)->header('Content-Type', 'image/svg+xml');
    }
}
