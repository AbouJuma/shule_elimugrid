<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreOnlineExamRequest;
use App\Models\OnlineExam;
use App\Services\OnlineExamService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class OnlineExamController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('online_exams.view');

        $exams = OnlineExam::query()
            ->with(['schoolClass', 'subject'])
            ->withCount('questions')
            ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->latest()
            ->get();

        return response()->json(['data' => $exams->map(fn ($e) => $this->summary($e))]);
    }

    public function store(StoreOnlineExamRequest $request, TenantContext $tenant, OnlineExamService $exams)
    {
        $exam = $exams->create($tenant->assertBound(), $request->validated(), $request->user());

        return response()->json(['data' => $this->detail($exam)], 201);
    }

    public function show(OnlineExam $exam)
    {
        $this->authorize('online_exams.view');

        return response()->json(['data' => $this->detail($exam->load('questions.choices'))]);
    }

    public function publish(OnlineExam $exam, OnlineExamService $exams)
    {
        $this->authorize('online_exams.manage');

        $exams->publish($exam);

        return response()->json(['data' => $this->summary($exam->fresh())]);
    }

    public function destroy(OnlineExam $exam)
    {
        $this->authorize('online_exams.manage');

        $exam->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    protected function summary(OnlineExam $exam): array
    {
        return [
            'id' => $exam->uuid,
            'title' => $exam->title,
            'mode' => $exam->mode,
            'status' => $exam->status,
            'question_count' => $exam->questions_count ?? $exam->questions()->count(),
            'school_class' => $exam->schoolClass ? ['id' => $exam->schoolClass->id, 'name' => $exam->schoolClass->name] : null,
            'subject' => $exam->subject ? ['id' => $exam->subject->id, 'name' => $exam->subject->name] : null,
        ];
    }

    /** Includes which choice is correct — staff-only, never sent to a student taking the exam. */
    protected function detail(OnlineExam $exam): array
    {
        return [
            ...$this->summary($exam),
            'description' => $exam->description,
            'time_limit_minutes' => $exam->time_limit_minutes,
            'questions' => $exam->questions->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'points' => $q->points,
                'choices' => $q->choices->map(fn ($c) => [
                    'id' => $c->id, 'choice_text' => $c->choice_text, 'is_correct' => $c->is_correct,
                ]),
            ]),
        ];
    }
}
