<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Academics\StoreSubjectRequest;
use App\Http\Requests\Academics\UpdateSubjectRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('subjects.view');

        return view('school-admin.subjects.index', [
            'subjects' => Subject::query()
                ->with('classes')
                ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                    'name', 'like', '%'.$request->string('search').'%'
                ))
                ->orderBy('name')
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'classes' => SchoolClass::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSubjectRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        $subject = Subject::create([
            'school_id' => $tenant->id(),
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
        ]);

        if (! empty($data['school_class_ids'])) {
            $subject->classes()->sync($data['school_class_ids']);
        }

        return redirect()->route('school-admin.subjects.index')->with('status', __('Subject ":p1" created.', ['p1' => $subject->name]));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject)
    {
        $data = $request->validated();

        $subject->update(['name' => $data['name'], 'code' => $data['code'] ?? null]);
        $subject->classes()->sync($data['school_class_ids'] ?? []);

        return redirect()->route('school-admin.subjects.index')->with('status', __('Subject ":p1" updated.', ['p1' => $subject->name]));
    }

    public function destroy(Subject $subject)
    {
        $this->authorize('subjects.manage');

        if (DB::table('exam_subjects')->where('subject_id', $subject->id)->exists()) {
            return back()->withErrors(['subject' => 'This subject has exam records — it can\'t be deleted.']);
        }

        $subject->delete();

        return redirect()->route('school-admin.subjects.index')->with('status', __('Subject ":p1" deleted.', ['p1' => $subject->name]));
    }
}
