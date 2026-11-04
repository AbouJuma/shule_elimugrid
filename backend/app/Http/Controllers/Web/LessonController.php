<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Learning\StoreLessonRequest;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\LessonService;
use App\Support\OwnStudents;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('lessons.view');

        return view('school-admin.lessons.index', [
            'lessons' => Lesson::query()
                ->with(['schoolClass', 'subject', 'creator'])
                ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'classes' => SchoolClass::query()->orderBy('name')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLessonRequest $request, TenantContext $tenant, LessonService $lessons)
    {
        $lesson = $lessons->create($tenant->assertBound(), $request->validated(), $request->file('video'), $request->user());

        return redirect()->route('school-admin.lessons.index')->with('status', __('Lesson ":p1" posted.', ['p1' => $lesson->title]));
    }

    public function destroy(Lesson $lesson, LessonService $lessons)
    {
        $this->authorize('lessons.manage');

        $lessons->delete($lesson);

        return redirect()->route('school-admin.lessons.index')->with('status', 'Lesson deleted.');
    }

    /**
     * Streams the uploaded video. Reachable by staff (lessons.view) or, for
     * their own child's/own class's lesson, a parent/student with no
     * school-wide permission at all — same dual-audience shape as
     * ResultsController::studentPdf(). response()->file() emits a
     * BinaryFileResponse, which handles Range requests itself, so seeking
     * within the video works without anything extra here.
     */
    public function video(Request $request, Lesson $lesson)
    {
        if (! $request->user()->can('lessons.view')) {
            $owns = OwnStudents::query($request->user())->where('school_class_id', $lesson->school_class_id)->exists();
            abort_unless($owns, 403);
        }

        abort_unless($lesson->video_path && Storage::disk('public')->exists($lesson->video_path), 404);

        return response()->file(Storage::disk('public')->path($lesson->video_path));
    }
}
