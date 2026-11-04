<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Learning\StoreHomeworkRequest;
use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\HomeworkService;
use App\Support\OwnStudents;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomeworkController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('homework.view');

        return view('school-admin.homework.index', [
            'homeworks' => Homework::query()
                ->with(['schoolClass', 'subject', 'creator'])
                ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'classes' => SchoolClass::query()->orderBy('name')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreHomeworkRequest $request, TenantContext $tenant, HomeworkService $homework)
    {
        $item = $homework->create($tenant->assertBound(), $request->validated(), $request->file('attachment'), $request->user());

        return redirect()->route('school-admin.homework.index')->with('status', __('Homework ":p1" posted.', ['p1' => $item->title]));
    }

    public function destroy(Homework $homework, HomeworkService $service)
    {
        $this->authorize('homework.manage');

        $service->delete($homework);

        return redirect()->route('school-admin.homework.index')->with('status', 'Homework deleted.');
    }

    public function attachment(Request $request, Homework $homework)
    {
        if (! $request->user()->can('homework.view')) {
            $owns = OwnStudents::query($request->user())->where('school_class_id', $homework->school_class_id)->exists();
            abort_unless($owns, 403);
        }

        abort_unless($homework->attachment_path && Storage::disk('public')->exists($homework->attachment_path), 404);

        return response()->file(Storage::disk('public')->path($homework->attachment_path));
    }
}
