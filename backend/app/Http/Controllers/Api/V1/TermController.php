<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Term;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Terms of an academic year (up to four), for the mobile app. */
class TermController extends Controller
{
    public const MAX_TERMS_PER_YEAR = 4;

    public function index()
    {
        $this->authorize('academic_years.view');

        return response()->json(['data' => Term::query()->with('academicYear')->orderByDesc('start_date')->get()->map(fn ($t) => $this->resource($t))->values()]);
    }

    public function store(Request $request, TenantContext $tenant)
    {
        $this->authorize('academic_years.manage');
        $schoolId = $tenant->assertBound()->id;

        $data = $request->validate([
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);

        if ($year->terms()->count() >= self::MAX_TERMS_PER_YEAR) {
            throw ValidationException::withMessages(['name' => [__('A school year can have at most :n terms.', ['n' => self::MAX_TERMS_PER_YEAR])]]);
        }

        if ($year->terms()->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => [__('This year already has a term with that name.')]]);
        }

        $term = DB::transaction(function () use ($data) {
            if ($data['is_current'] ?? false) {
                Term::query()->update(['is_current' => false]);
            }

            return Term::create($data);
        });

        return response()->json(['data' => $this->resource($term->load('academicYear'))], 201);
    }

    public function update(Request $request, int $term, TenantContext $tenant)
    {
        $this->authorize('academic_years.manage');
        $model = Term::query()->findOrFail($term);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        if ($model->academicYear->terms()->where('name', $data['name'])->where('id', '!=', $model->id)->exists()) {
            throw ValidationException::withMessages(['name' => [__('This year already has a term with that name.')]]);
        }

        DB::transaction(function () use ($data, $model) {
            if ($data['is_current'] ?? false) {
                Term::query()->where('id', '!=', $model->id)->update(['is_current' => false]);
            }

            $model->update($data);
        });

        return response()->json(['data' => $this->resource($model->fresh('academicYear'))]);
    }

    public function destroy(int $term)
    {
        $this->authorize('academic_years.manage');
        $model = Term::query()->findOrFail($term);

        if (Exam::query()->where('term_id', $model->id)->exists()) {
            throw ValidationException::withMessages(['term' => [__('This term has exams — it can\'t be deleted.')]]);
        }

        $model->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    protected function resource(Term $t): array
    {
        return [
            'id' => $t->id, 'name' => $t->name, 'academic_year_id' => $t->academic_year_id, 'year' => $t->academicYear?->name,
            'start_date' => $t->start_date?->toDateString(), 'end_date' => $t->end_date?->toDateString(), 'is_current' => (bool) $t->is_current,
        ];
    }
}
