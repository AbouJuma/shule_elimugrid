<?php

namespace Tests\Feature\Learning;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** The portal pages themselves, as an admin would actually load and submit them (not the JSON API). */
class LearningHubWebTest extends TestCase
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
        AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);

        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        return compact('school', 'class', 'subject', 'admin');
    }

    public function test_the_three_index_pages_render_for_an_admin(): void
    {
        $d = $this->scenario();

        $this->actingAs($d['admin'])->get('/lessons')->assertOk()->assertSee('Post Lesson', false);
        $this->actingAs($d['admin'])->get('/homework')->assertOk()->assertSee('Post Homework', false);
        $this->actingAs($d['admin'])->get('/online-exams')->assertOk()->assertSee('Create Online Exam', false);
    }

    public function test_the_online_exam_form_fields_the_page_js_produces_are_accepted_and_render_on_the_show_page(): void
    {
        $d = $this->scenario();

        // Exactly the field names index.blade.php's submit handler assigns
        // (questions[qi][...], choices[qi][ci][...]) — proves the portal
        // form and the backend's expectations actually agree.
        $response = $this->actingAs($d['admin'])->post('/online-exams', [
            'school_class_id' => $d['class']->id,
            'subject_id' => $d['subject']->id,
            'title' => 'Fractions Quiz',
            'mode' => 'graded',
            'questions' => [
                [
                    'question_text' => 'What is 1/2 + 1/4?',
                    'points' => '2',
                    'choices' => [
                        ['choice_text' => '3/4', 'is_correct' => '1'],
                        ['choice_text' => '1/2'],
                    ],
                ],
            ],
        ]);
        $response->assertRedirect();

        $exam = \App\Models\OnlineExam::firstOrFail();
        $this->assertSame('Fractions Quiz', $exam->title);
        $this->assertSame(1, $exam->questions()->count());
        $this->assertSame(2, $exam->questions->first()->points);

        $show = $this->actingAs($d['admin'])->get(route('school-admin.online-exams.show', $exam));
        $show->assertOk()->assertSee('What is 1/2 + 1/4?', false)->assertSee('3/4', false);

        $this->actingAs($d['admin'])->patch(route('school-admin.online-exams.publish', $exam))->assertRedirect();
        $this->assertSame('published', $exam->fresh()->status);
    }
}
