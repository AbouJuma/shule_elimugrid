<?php

namespace App\Services;

use App\Models\OnlineExam;
use App\Models\OnlineExamAnswer;
use App\Models\OnlineExamAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineExamService
{
    /**
     * @param  array{school_class_id: int, subject_id?: ?int, title: string, description?: ?string, mode: string, time_limit_minutes?: ?int, questions: array}  $data
     */
    public function create(School $school, array $data, User $creator): OnlineExam
    {
        return DB::transaction(function () use ($school, $data, $creator) {
            $exam = OnlineExam::create([
                'school_id' => $school->id,
                'school_class_id' => $data['school_class_id'],
                'subject_id' => $data['subject_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'mode' => $data['mode'],
                'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                'status' => 'draft',
                'created_by' => $creator->id,
            ]);

            foreach ($data['questions'] as $qi => $question) {
                $q = $exam->questions()->create([
                    'question_text' => $question['question_text'],
                    'points' => $question['points'] ?? 1,
                    'sort_order' => $qi,
                ]);

                foreach ($question['choices'] as $ci => $choice) {
                    $q->choices()->create([
                        'choice_text' => $choice['choice_text'],
                        'is_correct' => filter_var($choice['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'sort_order' => $ci,
                    ]);
                }
            }

            return $exam->load('questions.choices');
        });
    }

    public function publish(OnlineExam $exam): void
    {
        $exam->update(['status' => 'published']);
    }

    /**
     * Starts (or resumes an in-progress) attempt. A 'graded' exam refuses a
     * new attempt once one has been submitted — the whole point of "graded"
     * is that it's a single official result, not endlessly retakeable.
     */
    public function startAttempt(OnlineExam $exam, Student $student): OnlineExamAttempt
    {
        abort_unless($exam->isPublished(), 404, 'This exam is not open yet.');

        if ($exam->isGraded()) {
            $alreadySubmitted = $exam->attempts()
                ->where('student_id', $student->id)
                ->whereNotNull('submitted_at')
                ->exists();

            if ($alreadySubmitted) {
                throw ValidationException::withMessages([
                    'exam' => [__('This exam has already been completed — only one attempt is allowed.')],
                ]);
            }

            $inProgress = $exam->attempts()
                ->where('student_id', $student->id)
                ->whereNull('submitted_at')
                ->first();

            if ($inProgress) {
                return $inProgress;
            }
        }

        return OnlineExamAttempt::create([
            'school_id' => $exam->school_id,
            'online_exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{question_id: int, choice_id: ?int}>  $answers
     */
    public function submitAttempt(OnlineExamAttempt $attempt, array $answers): OnlineExamAttempt
    {
        abort_if($attempt->isSubmitted(), 422, 'This attempt was already submitted.');

        $exam = $attempt->exam()->with('questions.choices')->firstOrFail();
        $answersByQuestion = collect($answers)->keyBy('question_id');

        $score = 0;
        $maxScore = 0;

        DB::transaction(function () use ($exam, $attempt, $answersByQuestion, &$score, &$maxScore) {
            foreach ($exam->questions as $question) {
                $maxScore += $question->points;

                $chosenId = $answersByQuestion->get($question->id)['choice_id'] ?? null;
                // firstWhere against THIS question's own choices only — a
                // choice id belonging to a different question can never
                // match here, so it can't be smuggled in as "correct".
                $choice = $chosenId ? $question->choices->firstWhere('id', $chosenId) : null;
                $isCorrect = (bool) $choice?->is_correct;

                if ($isCorrect) {
                    $score += $question->points;
                }

                OnlineExamAnswer::updateOrCreate(
                    ['online_exam_attempt_id' => $attempt->id, 'online_exam_question_id' => $question->id],
                    ['online_exam_choice_id' => $choice?->id, 'is_correct' => $isCorrect],
                );
            }

            $attempt->update(['submitted_at' => now(), 'score' => $score, 'max_score' => $maxScore]);
        });

        return $attempt->fresh(['answers.question.choices']);
    }
}
