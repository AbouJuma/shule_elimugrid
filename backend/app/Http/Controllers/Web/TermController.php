<?php

namespace App\Http\Controllers\Web;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Term;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TermController extends \App\Http\Controllers\Controller
{
    /** A school year has three to four terms. */
    public const MAX_TERMS_PER_YEAR = 4;

    public function index()
    {
        $this->authorize('academic_years.view');

        return view('school-admin.terms.index', [
            'years' => AcademicYear::query()->with(['terms' => fn ($q) => $q->orderBy('start_date')])->orderByDesc('start_date')->get(),
            'maxTerms' => self::MAX_TERMS_PER_YEAR,
        ]);
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

        $year = AcademicYear::findOrFail($data['academic_year_id']);

        if ($year->terms()->count() >= self::MAX_TERMS_PER_YEAR) {
            return back()->withErrors(['name' => 'A school year can have at most '.self::MAX_TERMS_PER_YEAR.' terms.'])->withInput();
        }

        if ($year->terms()->where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'This year already has a term with that name.'])->withInput();
        }

        DB::transaction(function () use ($data) {
            if ($data['is_current'] ?? false) {
                Term::query()->update(['is_current' => false]);
            }

            Term::create($data);
        });

        return redirect()->route('school-admin.terms.index')->with('status', __('Term ":p1" added.', ['p1' => $data['name']]));
    }

    public function update(Request $request, Term $term)
    {
        $this->authorize('academic_years.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        if (Term::where('academic_year_id', $term->academic_year_id)->where('name', $data['name'])->where('id', '!=', $term->id)->exists()) {
            return back()->withErrors(['name' => 'This year already has a term with that name.']);
        }

        DB::transaction(function () use ($data, $term) {
            if ($data['is_current'] ?? false) {
                Term::query()->where('id', '!=', $term->id)->update(['is_current' => false]);
            }

            $term->update([...$data, 'is_current' => (bool) ($data['is_current'] ?? false)]);
        });

        return redirect()->route('school-admin.terms.index')->with('status', __('Term ":p1" updated.', ['p1' => $term->name]));
    }

    public function destroy(Term $term)
    {
        $this->authorize('academic_years.manage');

        if (Exam::where('term_id', $term->id)->exists()) {
            return back()->withErrors(['term' => 'This term already has exams — it can\'t be deleted.']);
        }

        $term->delete();

        return redirect()->route('school-admin.terms.index')->with('status', 'Term deleted.');
    }
}
