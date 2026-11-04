<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\ReportCardService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Termly results for the mobile app: the ranked class summary plus a link
 * to each student's printable report card (the shared PDF route).
 */
class ResultsController extends Controller
{
    public function terms()
    {
        $this->authorize('results.view');

        return response()->json(['data' => Term::query()->with('academicYear')->orderByDesc('start_date')->get()->map(fn ($t) => [
            'id' => $t->id, 'name' => $t->name, 'year' => $t->academicYear?->name, 'is_current' => $t->is_current,
        ])->values()]);
    }

    public function index(Request $request, TenantContext $tenant, ReportCardService $reports)
    {
        $this->authorize('results.view');

        $request->validate(['term_id' => ['required', 'integer'], 'school_class_id' => ['required', 'integer']]);

        $term = Term::findOrFail($request->integer('term_id'));
        $class = SchoolClass::findOrFail($request->integer('school_class_id'));

        $report = $reports->forClass($tenant->assertBound(), $class, $term);

        return response()->json(['data' => [
            'term' => ['id' => $term->id, 'name' => $term->name],
            'class' => ['id' => $class->id, 'name' => $class->name],
            'class_size' => $report['class_size'],
            'class_average' => $report['class_average'],
            'exams_count' => $report['exams']->count(),
            'exams' => $report['exams']->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'weight' => (float) $e->weight])->values(),
            'class_pdf_url' => route('school-admin.results.class-pdf', ['term_id' => $term->id, 'school_class_id' => $class->id]),
            'students' => $report['cards']
                ->sortBy(fn ($c) => $c['position'] ?? PHP_INT_MAX)->values()
                ->map(fn ($c) => [
                    'id' => $c['student']->uuid,
                    'name' => $c['student']->fullName(),
                    'admission_number' => $c['student']->admission_number,
                    'position' => $c['position'],
                    'average' => $c['average'],
                    'total' => $c['total'],
                    'grade' => $c['grade'],
                    'attendance_rate' => $c['attendance']['rate'],
                    'lines' => $c['lines']->map(fn ($l) => [
                        'subject' => $l['subject'], 'percent' => $l['percent'], 'grade' => $l['grade'],
                        'exam_scores' => $report['exams']->map(fn ($e) => $l['by_exam'][$e->id] ?? null)->values(),
                        'class_average' => $l['class_average'], 'position' => $l['position'],
                    ])->values(),
                    'pdf_url' => route('school-admin.results.student-pdf', [$c['student'], 'term_id' => $term->id]),
                ])->values(),
        ]]);
    }
}
