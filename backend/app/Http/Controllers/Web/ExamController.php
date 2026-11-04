<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Academics\AddExamSubjectRequest;
use App\Http\Requests\Academics\StoreExamRequest;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class ExamController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('exams.view');

        return view('school-admin.exams.index', [
            'exams' => Exam::query()
                ->with(['classes', 'academicYear'])
                ->when($request->integer('school_class_id'), fn ($q, $id) => $q->whereHas('classes', fn ($q) => $q->where('school_classes.id', $id)))
                ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'classes' => SchoolClass::query()->orderBy('name')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'terms' => \App\Models\Term::query()->with('academicYear')->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(StoreExamRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();
        $classIds = $data['school_class_ids'];
        unset($data['school_class_ids']);

        $exam = Exam::create(['school_id' => $tenant->id(), ...$data]);
        $exam->classes()->attach($classIds);

        return redirect()->route('school-admin.exams.show', $exam)->with('status', __('Exam ":p1" created.', ['p1' => $exam->name]));
    }

    public function show(Exam $exam)
    {
        $this->authorize('exams.view');

        $exam->load(['classes', 'academicYear', 'subjects']);

        // Subjects taught in ANY of this exam's classes that aren't
        // already attached to the exam — the only sensible choices for
        // "Add Subject" (a subject only one of several classes offers is
        // still fine to add; marks entry is scoped per class anyway).
        $classIds = $exam->classes->pluck('id');
        $availableSubjects = Subject::query()
            ->whereHas('classes', fn ($q) => $q->whereIn('school_classes.id', $classIds))
            ->get()
            ->diffKeys($exam->subjects->keyBy('id'));

        return view('school-admin.exams.show', [
            'exam' => $exam,
            'availableSubjects' => $availableSubjects,
        ]);
    }

    public function addSubject(AddExamSubjectRequest $request, Exam $exam)
    {
        $data = $request->validated();

        $exam->subjects()->syncWithoutDetaching([
            $data['subject_id'] => ['max_marks' => $data['max_marks']],
        ]);

        return redirect()->route('school-admin.exams.show', $exam)->with('status', 'Subject added to exam.');
    }

    public function publish(Exam $exam)
    {
        $this->authorize('exams.publish');

        $exam->update(['status' => 'published']);

        return redirect()->route('school-admin.exams.show', $exam)->with('status', 'Exam published.');
    }
}
