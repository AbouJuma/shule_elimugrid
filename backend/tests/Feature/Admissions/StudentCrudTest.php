<?php

namespace Tests\Feature\Admissions;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StudentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithStudent(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $class, $student, $admin];
    }

    public function test_admin_can_update_student_details(): void
    {
        [, , $student, $admin] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->put("/students/{$student->uuid}", [
            'first_name' => 'Jonathan', 'last_name' => 'Doe', 'gender' => 'male', 'date_of_birth' => '2015-01-01',
        ])->assertRedirect(route('school-admin.students.index'));

        $this->assertSame('Jonathan', $student->fresh()->first_name);
    }

    public function test_admin_can_archive_a_student_without_losing_the_record(): void
    {
        [, , $student, $admin] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->patch("/students/{$student->uuid}/archive", ['status' => 'graduated', 'reason' => 'Completed Form 4'])
            ->assertRedirect(route('school-admin.students.index'));

        $this->assertSame('graduated', $student->fresh()->status);
        $this->assertDatabaseHas('student_status_changes', [
            'student_id' => $student->id, 'from_status' => 'active', 'to_status' => 'graduated', 'reason' => 'Completed Form 4',
        ]);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'admission_number' => 'STU-1']);
    }

    public function test_a_status_change_without_a_reason_is_rejected(): void
    {
        [, , $student, $admin] = $this->makeSchoolWithStudent();

        $this->from('/students')->actingAs($admin)->patch("/students/{$student->uuid}/archive", ['status' => 'withdrawn'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('active', $student->fresh()->status);
        $this->assertDatabaseCount('student_status_changes', 0);
    }

    public function test_students_can_be_filtered_by_class_and_stream(): void
    {
        [$school, $class, $student, $admin] = $this->makeSchoolWithStudent();
        $stream = \App\Models\Stream::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Blue']);
        $student->update(['stream_id' => $stream->id]);
        $otherClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 2']);
        Student::create([
            'school_id' => $school->id, 'school_class_id' => $otherClass->id, 'academic_year_id' => $student->academic_year_id,
            'admission_number' => 'STU-9', 'first_name' => 'Zed', 'last_name' => 'Other',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        $this->actingAs($admin)->get("/students?school_class_id={$class->id}")
            ->assertOk()->assertSee('John Doe')->assertDontSee('Zed Other');

        $this->actingAs($admin)->get("/students?stream_id={$stream->id}")
            ->assertOk()->assertSee('John Doe')->assertDontSee('Zed Other');
    }

    public function test_teacher_cannot_archive_a_student(): void
    {
        [$school, , $student] = $this->makeSchoolWithStudent();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-students@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->patch("/students/{$student->uuid}/archive", ['status' => 'graduated'])
            ->assertForbidden();
    }
}
