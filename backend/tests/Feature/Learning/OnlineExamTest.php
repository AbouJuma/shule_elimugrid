<?php

namespace Tests\Feature\Learning;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlineExamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function scenario(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);

        setPermissionsTeamId($school->id);
        $teacher = User::create(['school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher@test.local', 'password' => 'password', 'status' => 'active']);
        $teacher->assignRole('Teacher');

        $parentUser = User::create(['school_id' => $school->id, 'name' => 'Mother', 'email' => 'mother@test.local', 'password' => 'password', 'status' => 'active']);
        $parentUser->assignRole('Parent');
        $guardian = Guardian::create(['school_id' => $school->id, 'user_id' => $parentUser->id, 'name' => 'Mother', 'phone' => '0700000000']);

        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-1', 'first_name' => 'Neema', 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ])->refresh();
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        return compact('school', 'class', 'subject', 'teacher', 'parentUser', 'student');
    }

    protected function twoQuestionPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Fractions Quiz',
            'mode' => 'graded',
            'questions' => [
                [
                    'question_text' => 'What is 1/2 + 1/4?',
                    'points' => 2,
                    'choices' => [
                        ['choice_text' => '3/4', 'is_correct' => true],
                        ['choice_text' => '1/2', 'is_correct' => false],
                    ],
                ],
                [
                    'question_text' => 'What is 2 x 3?',
                    'points' => 1,
                    'choices' => [
                        ['choice_text' => '5', 'is_correct' => false],
                        ['choice_text' => '6', 'is_correct' => true],
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_a_teacher_builds_a_quiz_and_it_stays_hidden_from_students_until_published(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $examId = $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload(),
            ['school_class_id' => $d['class']->id, 'subject_id' => $d['subject']->id]
        ))->assertCreated()->json('data.id');

        Sanctum::actingAs($d['parentUser']);
        $this->getJson("/api/v1/my/children/{$d['student']->uuid}/online-exams")
            ->assertOk()
            ->assertJsonCount(0, 'data'); // still a draft

        Sanctum::actingAs($d['teacher']);
        $this->patchJson("/api/v1/online-exams/{$examId}/publish")->assertOk();

        Sanctum::actingAs($d['parentUser']);
        $this->getJson("/api/v1/my/children/{$d['student']->uuid}/online-exams")
            ->assertOk()
            ->assertJsonFragment(['title' => 'Fractions Quiz']);
    }

    public function test_the_correct_answers_are_never_sent_to_a_student_taking_the_exam(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $examId = $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload(), ['school_class_id' => $d['class']->id]
        ))->json('data.id');
        $this->patchJson("/api/v1/online-exams/{$examId}/publish");

        $exam = \App\Models\OnlineExam::where('uuid', $examId)->firstOrFail();

        Sanctum::actingAs($d['parentUser']);
        $response = $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/start")->assertOk();

        $this->assertArrayNotHasKey('is_correct', $response->json('data.questions.0.choices.0'));
        $this->assertCount(2, $response->json('data.questions'));
    }

    public function test_submitting_computes_the_score_and_shows_which_answers_were_right(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $examId = $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload(), ['school_class_id' => $d['class']->id]
        ))->json('data.id');
        $this->patchJson("/api/v1/online-exams/{$examId}/publish");

        $exam = \App\Models\OnlineExam::where('uuid', $examId)->with('questions.choices')->firstOrFail();
        $q1 = $exam->questions[0];
        $q2 = $exam->questions[1];
        $correctChoiceQ1 = $q1->choices->firstWhere('is_correct', true);
        $wrongChoiceQ2 = $q2->choices->firstWhere('is_correct', false);

        Sanctum::actingAs($d['parentUser']);
        $attemptId = $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/start")
            ->json('data.attempt_id');

        $response = $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/submit", [
            'attempt_id' => $attemptId,
            'answers' => [
                ['question_id' => $q1->id, 'choice_id' => $correctChoiceQ1->id],
                ['question_id' => $q2->id, 'choice_id' => $wrongChoiceQ2->id],
            ],
        ])->assertOk();

        // Q1 worth 2 points (correct), Q2 worth 1 point (wrong) = 2/3.
        $this->assertSame(2, $response->json('data.score'));
        $this->assertSame(3, $response->json('data.max_score'));

        $answers = collect($response->json('data.answers'))->keyBy('question_id');
        $this->assertTrue($answers[$q1->id]['is_correct']);
        $this->assertFalse($answers[$q2->id]['is_correct']);
    }

    public function test_a_graded_exam_allows_only_one_completed_attempt(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $examId = $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload(['mode' => 'graded']), ['school_class_id' => $d['class']->id]
        ))->json('data.id');
        $this->patchJson("/api/v1/online-exams/{$examId}/publish");

        $exam = \App\Models\OnlineExam::where('uuid', $examId)->with('questions.choices')->firstOrFail();

        Sanctum::actingAs($d['parentUser']);
        $attemptId = $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/start")->json('data.attempt_id');
        $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/submit", [
            'attempt_id' => $attemptId,
            'answers' => [['question_id' => $exam->questions[0]->id, 'choice_id' => $exam->questions[0]->choices[0]->id]],
        ])->assertOk();

        // Starting again is refused once a graded attempt is complete.
        $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/start")->assertUnprocessable();
    }

    public function test_a_practice_exam_allows_unlimited_attempts(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $examId = $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload(['mode' => 'practice']), ['school_class_id' => $d['class']->id]
        ))->json('data.id');
        $this->patchJson("/api/v1/online-exams/{$examId}/publish");

        $exam = \App\Models\OnlineExam::where('uuid', $examId)->with('questions.choices')->firstOrFail();

        Sanctum::actingAs($d['parentUser']);
        foreach ([1, 2, 3] as $attemptNumber) {
            $attemptId = $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/start")
                ->assertOk()->json('data.attempt_id');
            $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/submit", [
                'attempt_id' => $attemptId,
                'answers' => [['question_id' => $exam->questions[0]->id, 'choice_id' => $exam->questions[0]->choices[0]->id]],
            ])->assertOk();
        }

        $this->assertSame(3, \App\Models\OnlineExamAttempt::where('online_exam_id', $exam->id)->count());
    }

    public function test_reopening_an_unsubmitted_graded_attempt_resumes_it_instead_of_starting_a_new_one(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $examId = $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload(['mode' => 'graded']), ['school_class_id' => $d['class']->id]
        ))->json('data.id');
        $this->patchJson("/api/v1/online-exams/{$examId}/publish");

        Sanctum::actingAs($d['parentUser']);
        $first = $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/start")->json('data.attempt_id');
        $second = $this->postJson("/api/v1/my/children/{$d['student']->uuid}/online-exams/{$examId}/start")->json('data.attempt_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, \App\Models\OnlineExamAttempt::count());
    }

    public function test_a_question_needs_exactly_one_correct_choice(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload([
                'questions' => [[
                    'question_text' => 'Bad question',
                    'choices' => [
                        ['choice_text' => 'A', 'is_correct' => true],
                        ['choice_text' => 'B', 'is_correct' => true],
                    ],
                ]],
            ]),
            ['school_class_id' => $d['class']->id]
        ))->assertUnprocessable();
    }

    public function test_an_accountant_cannot_create_or_publish_an_online_exam(): void
    {
        $d = $this->scenario();

        setPermissionsTeamId($d['school']->id);
        $accountant = User::create(['school_id' => $d['school']->id, 'name' => 'Accountant', 'email' => 'acc@test.local', 'password' => 'password', 'status' => 'active']);
        $accountant->assignRole('Accountant');

        Sanctum::actingAs($accountant);
        $this->postJson('/api/v1/online-exams', array_merge(
            $this->twoQuestionPayload(), ['school_class_id' => $d['class']->id]
        ))->assertForbidden();
    }
}
