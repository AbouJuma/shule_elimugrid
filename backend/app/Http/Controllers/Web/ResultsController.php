<?php

namespace App\Http\Controllers\Web;

use App\Models\AcademicYear;
use App\Models\ReportCardRemark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\GradingService;
use App\Services\ReportCardService;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ResultsController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant, ReportCardService $reports, GradingService $grading)
    {
        $this->authorize('results.view');
        $school = $tenant->assertBound();

        $terms = Term::query()->with('academicYear')->orderByDesc('start_date')->get();
        $classes = SchoolClass::query()->orderBy('order')->orderBy('name')->get();

        $term = $terms->firstWhere('id', $request->integer('term_id')) ?? $terms->firstWhere('is_current', true);
        $class = $classes->firstWhere('id', $request->integer('school_class_id'));

        $report = ($term && $class) ? $reports->forClass($school, $class, $term) : null;

        return view('school-admin.results.index', [
            'terms' => $terms,
            'classes' => $classes,
            'term' => $term,
            'class' => $class,
            'report' => $report,
            'scale' => $grading->scaleFor($school),
        ]);
    }

    public function studentPdf(Request $request, Student $student, TenantContext $tenant, ReportCardService $reports)
    {
        // Staff with results.view see any student; a parent or the student themselves see their own, and only published results.
        $own = ! $request->user()->can('results.view');

        if ($own) {
            abort_unless(\App\Support\OwnStudents::owns($request->user(), $student), 403);
        }

        $school = $tenant->assertBound();

        $term = Term::findOrFail($request->integer('term_id'));
        $class = SchoolClass::findOrFail($student->school_class_id);

        $data = $reports->cardFor($school, $class, $term, $student, publishedOnly: $own);
        abort_if($data === null, 404, 'This student has no report for the selected term.');
        abort_if($own && $data['exams']->isEmpty(), 404, 'No published results yet for this term.');

        return $this->render($school, $class, $term, $data, [$data['card']], "report-card-{$student->admission_number}.pdf");
    }

    public function classPdf(Request $request, TenantContext $tenant, ReportCardService $reports)
    {
        $this->authorize('results.view');
        $school = $tenant->assertBound();

        $term = Term::findOrFail($request->integer('term_id'));
        $class = SchoolClass::findOrFail($request->integer('school_class_id'));

        $data = $reports->forClass($school, $class, $term);
        abort_if($data['cards']->isEmpty(), 404, 'This class has no students to report on.');

        return $this->render($school, $class, $term, $data, $data['cards']->all(), "report-cards-{$class->name}-{$term->name}.pdf");
    }

    public function saveRemarks(Request $request, TenantContext $tenant)
    {
        $this->authorize('results.manage');
        $schoolId = $tenant->assertBound()->id;

        $data = $request->validate([
            'term_id' => ['required', Rule::exists('terms', 'id')->where('school_id', $schoolId)],
            'school_class_id' => ['required', 'integer'],
            'remarks' => ['required', 'array'],
            'remarks.*.class_teacher_remark' => ['nullable', 'string', 'max:500'],
            'remarks.*.head_teacher_remark' => ['nullable', 'string', 'max:500'],
        ]);

        $validStudents = Student::query()->where('school_class_id', $data['school_class_id'])->pluck('id')->all();

        DB::transaction(function () use ($data, $validStudents, $schoolId) {
            foreach ($data['remarks'] as $studentId => $remark) {
                if (! in_array((int) $studentId, $validStudents, true)) {
                    continue;
                }

                ReportCardRemark::withoutGlobalScopes()->updateOrCreate(
                    ['school_id' => $schoolId, 'student_id' => $studentId, 'term_id' => $data['term_id']],
                    [
                        'class_teacher_remark' => $remark['class_teacher_remark'] ?? null,
                        'head_teacher_remark' => $remark['head_teacher_remark'] ?? null,
                    ],
                );
            }
        });

        return redirect()->route('school-admin.results.index', [
            'term_id' => $data['term_id'], 'school_class_id' => $data['school_class_id'],
        ])->with('status', 'Remarks saved.');
    }

    public function updateGradeScale(Request $request, TenantContext $tenant, GradingService $grading)
    {
        $this->authorize('results.manage');

        $data = $request->validate([
            'scale' => ['required', 'array', 'min:2'],
            'scale.*.grade' => ['required', 'string', 'max:5'],
            'scale.*.min_percent' => ['required', 'integer', 'between:0,100'],
            'scale.*.remark' => ['nullable', 'string', 'max:50'],
            'scale.*.remark_sw' => ['nullable', 'string', 'max:50'],
        ]);

        $rows = collect($data['scale']);

        if (! $rows->contains(fn ($r) => (int) $r['min_percent'] === 0)) {
            return back()->withErrors(['scale' => 'One grade must start at 0% so every score has a grade.']);
        }

        if ($rows->pluck('min_percent')->duplicates()->isNotEmpty() || $rows->map(fn ($r) => strtoupper($r['grade']))->duplicates()->isNotEmpty()) {
            return back()->withErrors(['scale' => 'Grades and boundaries must each be unique.']);
        }

        $grading->replaceScale($tenant->assertBound(), $rows->all());

        return back()->with('status', 'Grade scale updated.');
    }

    protected function render($school, $class, $term, array $data, array $cards, string $filename)
    {
        $term->loadMissing('academicYear');

        return Pdf::loadView('results.report-card', [
            'school' => $school,
            'class' => $class,
            'term' => $term,
            'cards' => $cards,
            'classSize' => $data['class_size'],
            'classAverage' => $data['class_average'],
            'exams' => $data['exams'] ?? collect(),
            'scale' => $data['scale'],
        ])->setPaper('a4', 'portrait')->stream($filename);
    }
}
