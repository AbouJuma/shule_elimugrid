<?php

namespace Tests\Feature\Communications;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A parent or the student themselves can see the student's own published results and timetable — nothing else. */
class OwnResultsAndTimetableTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
        $this->school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
    }

    protected function user(string $name, string $role): User
    {
        setPermissionsTeamId($this->school->id);
        $u = User::create(['school_id' => $this->school->id, 'name' => $name, 'email' => str($name)->slug().random_int(1, 99999).'@test.local', 'password' => 'password', 'status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    /** Neema in Form 1 / Blue, with a mother who has a login and Neema has her own login too. */
    protected function scenario(): array
    {
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $blue = Stream::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Blue']);
        $term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1', 'start_date' => '2026-01-05', 'end_date' => '2026-04-01', 'is_current' => true]);

        $parentUser = $this->user('Asha Kileo', 'Parent');
        $guardian = Guardian::create(['school_id' => $this->school->id, 'user_id' => $parentUser->id, 'name' => 'Asha Kileo', 'phone' => '0712345678']);

        $studentUser = $this->user('Neema Kileo', 'Student');
        $student = Student::create([
            'school_id' => $this->school->id, 'school_class_id' => $class->id, 'stream_id' => $blue->id, 'academic_year_id' => $year->id, 'user_id' => $studentUser->id,
            'admission_number' => 'STU-1', 'first_name' => 'Neema', 'last_name' => 'Kileo', 'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ])->refresh();
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $math = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics']);
        $exam = Exam::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Midterm', 'status' => 'ongoing']);
        $exam->classes()->attach($class->id);
        DB::table('exam_subjects')->insert(['exam_id' => $exam->id, 'subject_id' => $math->id, 'max_marks' => 100, 'created_at' => now(), 'updated_at' => now()]);
        ExamMark::create(['school_id' => $this->school->id, 'exam_id' => $exam->id, 'subject_id' => $math->id, 'student_id' => $student->id, 'marks_obtained' => 72]);

        $teacher = $this->user('Mr Juma', 'Teacher');
        $period = TimetablePeriod::create(['school_id' => $this->school->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'sort_order' => 1]);
        TimetableEntry::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'stream_id' => $blue->id, 'day_of_week' => 1, 'timetable_period_id' => $period->id, 'subject_id' => $math->id, 'teacher_user_id' => $teacher->id]);

        return compact('parentUser', 'studentUser', 'student', 'exam', 'math', 'class', 'blue', 'period');
    }

    public function test_an_unpublished_exam_shows_nothing_yet_and_publishing_it_reveals_the_result(): void
    {
        $d = $this->scenario();
        Sanctum::actingAs($d['parentUser']);

        $this->getJson("/api/v1/my/children/{$d['student']->uuid}/results")->assertOk()->assertJsonPath('data.published', false);

        $d['exam']->update(['status' => 'published']);

        $r = $this->getJson("/api/v1/my/children/{$d['student']->uuid}/results")->assertOk();
        $r->assertJsonPath('data.published', true)->assertJsonPath('data.class_size', 1)
            ->assertJsonPath('data.term.name', 'Term 1')->assertJsonPath('data.lines.0.subject', 'Mathematics');
        $this->assertEqualsWithDelta(72.0, $r->json('data.average'), 0.01);
        $this->assertEqualsWithDelta(72.0, $r->json('data.lines.0.percent'), 0.01);

        $this->assertArrayNotHasKey('by_exam', $r->json('data.lines.0'));
    }

    public function test_the_student_can_see_their_own_result_too_and_it_is_the_same_data(): void
    {
        $d = $this->scenario();
        $d['exam']->update(['status' => 'published']);

        Sanctum::actingAs($d['studentUser']);
        $r = $this->getJson("/api/v1/my/children/{$d['student']->uuid}/results")->assertOk();
        $this->assertEqualsWithDelta(72.0, $r->json('data.average'), 0.01);

        // But not another student's.
        $stranger = Student::create(['school_id' => $this->school->id, 'school_class_id' => $d['class']->id, 'academic_year_id' => AcademicYear::first()->id, 'admission_number' => 'STU-9', 'first_name' => 'Other', 'last_name' => 'Kid', 'gender' => 'male', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10']);
        $this->getJson("/api/v1/my/children/{$stranger->uuid}/results")->assertNotFound();
    }

    public function test_the_pdf_report_card_only_shows_published_results_to_a_parent_but_any_to_staff(): void
    {
        $d = $this->scenario();
        Sanctum::actingAs($d['parentUser']);

        $this->get("/results/students/{$d['student']->uuid}/pdf?term_id={$d['exam']->term_id}")->assertNotFound();

        $d['exam']->update(['status' => 'published']);

        $this->get("/results/students/{$d['student']->uuid}/pdf?term_id={$d['exam']->term_id}")->assertOk();

        $admin = $this->user('Admin', 'School Administrator');
        $d['exam']->update(['status' => 'ongoing']);
        $this->actingAs($admin)->get("/results/students/{$d['student']->uuid}/pdf?term_id={$d['exam']->term_id}")->assertOk();
    }

    public function test_the_childs_timetable_shows_their_stream_and_whole_class_lessons(): void
    {
        $d = $this->scenario();
        Sanctum::actingAs($d['parentUser']);

        $r = $this->getJson("/api/v1/my/children/{$d['student']->uuid}/timetable")->assertOk();
        $r->assertJsonPath('data.entries.0.subject', 'Mathematics')->assertJsonPath('data.entries.0.teacher', 'Mr Juma');
    }

    public function test_a_student_login_can_be_created_and_reset_and_only_by_staff(): void
    {
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 2']);
        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'STU-5', 'first_name' => 'Juma', 'last_name' => 'Ali', 'gender' => 'male', 'date_of_birth' => '2013-01-01', 'admission_date' => '2026-01-10']);

        $teacher = $this->user('Teacher', 'Teacher');
        Sanctum::actingAs($teacher);
        $this->postJson("/api/v1/students/{$student->uuid}/account", ['email' => 'juma@student.test'])->assertForbidden();

        $admin = $this->user('Admin', 'School Administrator');
        Sanctum::actingAs($admin);
        $r = $this->postJson("/api/v1/students/{$student->uuid}/account", ['email' => 'juma@student.test'])->assertCreated();
        $password = $r->json('temporary_password');

        setPermissionsTeamId($this->school->id);
        $this->assertTrue($student->fresh()->user->hasRole('Student'));
        $this->assertCount(0, $student->fresh()->user->getAllPermissions());

        app('auth')->forgetGuards(); app('auth')->shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => 'juma@student.test', 'password' => $password, 'device_name' => 't'])->assertOk();

        Sanctum::actingAs($admin);
        $new = $this->postJson("/api/v1/students/{$student->uuid}/account/reset")->assertOk()->json('temporary_password');
        $this->assertNotSame($password, $new);
    }
}
