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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeworkTest extends TestCase
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

    public function test_a_teacher_posts_homework_with_a_due_date_and_the_parent_sees_it(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $this->postJson('/api/v1/homework', [
            'school_class_id' => $d['class']->id,
            'subject_id' => $d['subject']->id,
            'title' => 'Long Division Worksheet',
            'instructions' => 'Complete questions 1-10.',
            'due_date' => '2026-03-01',
        ])->assertCreated();

        Sanctum::actingAs($d['parentUser']);
        $response = $this->getJson("/api/v1/my/children/{$d['student']->uuid}/homework")->assertOk();
        $response->assertJsonFragment(['title' => 'Long Division Worksheet', 'due_date' => '2026-03-01']);
    }

    public function test_homework_can_carry_an_attachment_streamed_only_to_the_right_parent(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $this->postJson('/api/v1/homework', [
            'school_class_id' => $d['class']->id,
            'title' => 'Reading Sheet',
            'attachment' => UploadedFile::fake()->create('sheet.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $homework = \App\Models\Homework::firstOrFail();

        Sanctum::actingAs($d['parentUser']);
        $this->get(route('school-admin.homework.attachment', $homework))->assertOk();

        setPermissionsTeamId($d['school']->id);
        $strangerParent = User::create(['school_id' => $d['school']->id, 'name' => 'Stranger', 'email' => 'stranger@test.local', 'password' => 'password', 'status' => 'active']);
        $strangerParent->assignRole('Parent');
        \App\Models\Guardian::create(['school_id' => $d['school']->id, 'user_id' => $strangerParent->id, 'name' => 'Stranger', 'phone' => '0722222222']);

        Sanctum::actingAs($strangerParent);
        $this->get(route('school-admin.homework.attachment', $homework))->assertForbidden();
    }

    public function test_an_accountant_cannot_post_homework(): void
    {
        $d = $this->scenario();

        setPermissionsTeamId($d['school']->id);
        $accountant = User::create(['school_id' => $d['school']->id, 'name' => 'Accountant', 'email' => 'acc@test.local', 'password' => 'password', 'status' => 'active']);
        $accountant->assignRole('Accountant');

        Sanctum::actingAs($accountant);
        $this->postJson('/api/v1/homework', [
            'school_class_id' => $d['class']->id, 'title' => 'Not allowed',
        ])->assertForbidden();
    }
}
