<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** The portal students page: viewing a student's own and their guardians' details, and deleting a record. */
class PortalStudentDetailTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected SchoolClass $class;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
        $this->school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
        $this->year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);

        setPermissionsTeamId($this->school->id);
        $this->admin = User::create(['school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $this->admin->assignRole('School Administrator');
    }

    protected function student(): Student
    {
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678', 'occupation' => 'Nurse']);
        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id, 'admission_number' => 'STU-1', 'first_name' => 'Neema', 'last_name' => 'Kileo', 'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10'])->refresh();
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        return $student;
    }

    public function test_the_students_page_shows_the_guardians_details_for_viewing(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin)->get('/students')
            ->assertOk()
            ->assertSee('Neema Kileo')
            ->assertSee('Asha Kileo')
            ->assertSee('0712345678')
            ->assertSee('Nurse')
            ->assertSee($student->control_number);
    }

    public function test_a_parent_login_can_be_created_from_the_students_page_and_the_password_is_shown_once(): void
    {
        Queue::fake();
        $student = $this->student();
        $guardian = $student->guardians->first();

        $this->actingAs($this->admin)
            ->withHeaders(['referer' => '/students'])
            ->post("/guardians/{$guardian->id}/account", ['email' => 'asha@parent.test'])
            ->assertRedirect('/students');

        $this->assertNotNull($guardian->fresh()->user_id);

        $follow = $this->get('/students');
        $follow->assertSee('Parent login ready')->assertSee('asha@parent.test');
    }

    public function test_a_teacher_cannot_delete_a_student_but_an_admin_can_and_it_is_only_soft_deleted(): void
    {
        $student = $this->student();

        setPermissionsTeamId($this->school->id);
        $teacher = User::create(['school_id' => $this->school->id, 'name' => 'Teacher', 'email' => 'teacher@test.local', 'password' => 'password', 'status' => 'active']);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->delete("/students/{$student->uuid}")->assertForbidden();
        $this->assertNotSoftDeleted($student);

        $this->actingAs($this->admin)->delete("/students/{$student->uuid}")->assertRedirect(route('school-admin.students.index'));

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertNotNull(Student::withTrashed()->find($student->id), 'the row still exists');
        $this->assertNull(Student::find($student->id), 'but no longer in normal listings');

        // The flash message legitimately names the student ("Neema Kileo deleted."), so check the
        // row itself is gone via something unique to it, not the name.
        $this->actingAs($this->admin)->get('/students')->assertOk()->assertDontSee($student->control_number)->assertDontSee('STU-1');
    }
}
