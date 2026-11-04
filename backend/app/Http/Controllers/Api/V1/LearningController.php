<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\Lesson;
use App\Models\OnlineExam;
use App\Models\Student;
use App\Services\OnlineExamService;
use App\Support\OwnStudents;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Learning Hub, as seen by a signed-in parent (for a specific child) or
 * a student themselves — access is by the OwnStudents link alone, exactly
 * like ParentController's attendance/results/timetable, not a school-wide
 * permission. Lessons and homework are read-only here; online exams can
 * also be started and submitted.
 */
class LearningController extends Controller
{
    public function lessons(Request $request, string $student)
    {
        $child = $this->child($request, $student);

        $lessons = Lesson::where('school_class_id', $child->school_class_id)->with('subject')->latest()->get();

        return response()->json(['data' => $lessons->map(fn (Lesson $l) => [
            'id' => $l->uuid,
            'title' => $l->title,
            'description' => $l->description,
            'subject' => $l->subject?->name,
            'video_url' => $l->video_url,
            'video_stream_url' => $l->hasUploadedVideo() ? route('school-admin.lessons.video', $l) : null,
        ])->values()]);
    }

    public function homework(Request $request, string $student)
    {
        $child = $this->child($request, $student);

        $homework = Homework::where('school_class_id', $child->school_class_id)->with('subject')->latest()->get();

        return response()->json(['data' => $homework->map(fn (Homework $h) => [
            'id' => $h->uuid,
            'title' => $h->title,
            'instructions' => $h->instructions,
            'due_date' => $h->due_date?->toDateString(),
            'subject' => $h->subject?->name,
            'attachment_url' => $h->attachment_path ? route('school-admin.homework.attachment', $h) : null,
        ])->values()]);
    }

    public function onlineExams(Request $request, string $student)
    {
        $child = $this->child($request, $student);

        $exams = OnlineExam::where('school_class_id', $child->school_class_id)
            ->where('status', 'published')
            ->withCount('questions')
            ->with('subject')
            ->latest()
            ->get();

        $attempts = $child->onlineExamAttempts()->whereIn('online_exam_id', $exams->pluck('id'))->get()->groupBy('online_exam_id');

        return response()->json(['data' => $exams->map(function (OnlineExam $exam) use ($attempts) {
            $mine = $attempts->get($exam->id, collect());
            $submitted = $mine->firstWhere('submitted_at', '!=', null);
            $inProgress = $mine->firstWhere('submitted_at', null);

            return [
                'id' => $exam->uuid,
                'title' => $exam->title,
                'description' => $exam->description,
                'mode' => $exam->mode,
                'subject' => $exam->subject?->name,
                'question_count' => $exam->questions_count,
                'time_limit_minutes' => $exam->time_limit_minutes,
                // 'graded' exams show as done the instant one is submitted; 'practice' always stays open.
                'completed' => $exam->mode === 'graded' && $submitted !== null,
                'in_progress' => $inProgress !== null,
                'last_score' => $submitted ? ['score' => $submitted->score, 'max_score' => $submitted->max_score] : null,
            ];
        })->values()]);
    }

    public function startOnlineExam(Request $request, string $student, OnlineExam $exam, OnlineExamService $service)
    {
        $child = $this->child($request, $student);
        abort_unless($exam->school_class_id === $child->school_class_id, 404);

        try {
            $attempt = $service->startAttempt($exam, $child);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => [
            'attempt_id' => $attempt->id,
            'exam' => ['id' => $exam->uuid, 'title' => $exam->title, 'time_limit_minutes' => $exam->time_limit_minutes],
            // Never includes is_correct — that would give the answer away.
            'questions' => $exam->questions->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'choices' => $q->choices->map(fn ($c) => ['id' => $c->id, 'choice_text' => $c->choice_text])->values(),
            ])->values(),
        ]]);
    }

    public function submitOnlineExam(Request $request, string $student, OnlineExam $exam, OnlineExamService $service)
    {
        $child = $this->child($request, $student);
        abort_unless($exam->school_class_id === $child->school_class_id, 404);

        $data = $request->validate([
            'attempt_id' => ['required', 'integer'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.choice_id' => ['nullable', 'integer'],
        ]);

        $attempt = $exam->attempts()->where('student_id', $child->id)->findOrFail($data['attempt_id']);
        $attempt = $service->submitAttempt($attempt, $data['answers']);

        return response()->json(['data' => [
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'answers' => $attempt->answers->map(fn ($a) => [
                'question_id' => $a->online_exam_question_id,
                'choice_id' => $a->online_exam_choice_id,
                'is_correct' => $a->is_correct,
                'correct_choice_id' => $a->question->correctChoice()?->id,
            ])->values(),
        ]]);
    }

    protected function child(Request $request, string $uuid): Student
    {
        $student = Student::where('uuid', $uuid)->firstOrFail();
        abort_unless(OwnStudents::owns($request->user(), $student), 403);

        return $student;
    }
}
