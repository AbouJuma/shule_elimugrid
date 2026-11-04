<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreLessonRequest;
use App\Models\Lesson;
use App\Services\LessonService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('lessons.view');

        $lessons = Lesson::query()
            ->with(['schoolClass', 'subject'])
            ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->latest()
            ->get();

        return response()->json(['data' => $lessons->map($this->toArray(...))]);
    }

    public function store(StoreLessonRequest $request, TenantContext $tenant, LessonService $lessons)
    {
        $lesson = $lessons->create($tenant->assertBound(), $request->validated(), $request->file('video'), $request->user());

        return response()->json(['data' => $this->toArray($lesson)], 201);
    }

    public function destroy(Lesson $lesson, LessonService $lessons)
    {
        $this->authorize('lessons.manage');

        $lessons->delete($lesson);

        return response()->json(['data' => ['deleted' => true]]);
    }

    protected function toArray(Lesson $lesson): array
    {
        return [
            'id' => $lesson->uuid,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'school_class' => $lesson->schoolClass ? ['id' => $lesson->schoolClass->id, 'name' => $lesson->schoolClass->name] : null,
            'subject' => $lesson->subject ? ['id' => $lesson->subject->id, 'name' => $lesson->subject->name] : null,
            'video_url' => $lesson->video_url,
            'video_stream_url' => $lesson->hasUploadedVideo() ? route('school-admin.lessons.video', $lesson) : null,
            'created_at' => $lesson->created_at->toIso8601String(),
        ];
    }
}
