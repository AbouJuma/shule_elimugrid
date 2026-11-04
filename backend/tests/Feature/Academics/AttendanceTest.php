<?php

namespace Tests\Feature\Academics;

use App\Models\AcademicYear;
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

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithClassAndStudents(string $schoolName, int $studentCount = 3): array
    {
        $school = School::create(['name' => $schoolName, 'slug' => str($schoolName)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        $students = collect(range(1, $studentCount))->map(fn ($i) => Student::create([
            'school_id' => $school->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $year->id,
            'admission_number' => "STU-{$schoolName}-{$i}",
            'first_name' => "Student{$i}",
            'last_name' => 'Test',
            'gender' => 'male',
            'date_of_birth' => '2015-01-01',
            'admission_date' => '2026-01-15',
        ]));

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$schoolName} Admin",
            'email' => str($schoolName)->slug().'-admin@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        $teacher = User::create([
            'school_id' => $school->id, 'name' => "{$schoolName} Teacher",
            'email' => str($schoolName)->slug().'-teacher@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        return [$school, $class, $students, $admin, $teacher];
    }

    public function test_teacher_can_list_classes_for_attendance_without_classes_view_permission(): void
    {
        [$school, $class, , , $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise');

        // Teacher holds attendance.mark/view but never classes.view — the
        // register's class picker must not depend on that permission.
        $this->assertFalse($teacher->can('classes.view'));

        Sanctum::actingAs($teacher);
        $response = $this->getJson('/api/v1/attendance/classes')->assertOk();

        $response->assertJsonFragment(['id' => $class->id, 'name' => $class->name]);
    }

    public function test_teacher_can_mark_attendance_for_their_class(): void
    {
        [$school, $class, $students, , $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise');

        Sanctum::actingAs($teacher);

        $records = $students->map(fn ($s, $i) => [
            'student_id' => $s->id,
            'status' => $i === 0 ? 'absent' : 'present',
        ])->values()->all();

        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'date' => '2026-02-01',
            'records' => $records,
        ])->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[0]->id, 'date' => '2026-02-01', 'status' => 'absent',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[1]->id, 'date' => '2026-02-01', 'status' => 'present',
        ]);
    }

    public function test_a_saved_register_locks_itself_and_resubmitting_it_is_rejected_until_unlocked(): void
    {
        [$school, $class, $students, $admin, $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise', 1);

        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'absent']],
        ])->assertOk();

        // Same register, same date — locked the instant it was first saved.
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[0]->id, 'date' => '2026-02-01', 'status' => 'absent',
        ]);

        // A Teacher can't unlock it themselves...
        $this->postJson('/api/v1/attendance/unlock', [
            'school_class_id' => $class->id, 'date' => '2026-02-01',
        ])->assertForbidden();

        // ...but a School Administrator can, and marking it again then works.
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/attendance/unlock', [
            'school_class_id' => $class->id, 'date' => '2026-02-01',
        ])->assertOk();

        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertOk();

        $this->assertSame(1, \App\Models\AttendanceRecord::where('student_id', $students[0]->id)->count());
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[0]->id, 'date' => '2026-02-01', 'status' => 'present',
        ]);
    }

    public function test_locking_one_registers_class_or_subject_scope_does_not_affect_others(): void
    {
        [$school, $class, $students, , $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise', 1);
        $maths = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $english = Subject::create(['school_id' => $school->id, 'name' => 'English', 'code' => 'ENG']);
        $class->subjects()->attach([$maths->id, $english->id]);

        Sanctum::actingAs($teacher);

        // Locking the class-wide register for this date...
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id, 'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertOk();

        // ...doesn't lock Mathematics' register for the same date,
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id, 'subject_id' => $maths->id, 'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertOk();

        // ...nor English's, even though both are the "same date".
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id, 'subject_id' => $english->id, 'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertOk();

        // But Mathematics' own register, now locked, can't be resubmitted.
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id, 'subject_id' => $maths->id, 'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'absent']],
        ])->assertUnprocessable();

        // And a different date entirely is never affected.
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id, 'date' => '2026-02-02',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertOk();
    }

    public function test_register_view_shows_unmarked_students_as_null_status(): void
    {
        [$school, $class, $students, $admin] = $this->makeSchoolWithClassAndStudents('Sunrise', 2);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertOk();

        $response = $this->getJson("/api/v1/attendance?school_class_id={$class->id}&date=2026-02-01")->assertOk();

        $statuses = collect($response->json('data.students'))->pluck('status', 'id');
        $this->assertSame('present', $statuses[$students[0]->id]);
        $this->assertNull($statuses[$students[1]->id]);
    }

    public function test_teacher_cannot_mark_attendance_for_another_schools_class(): void
    {
        [$schoolA, $classA, $studentsA, , $teacherA] = $this->makeSchoolWithClassAndStudents('School A');
        [$schoolB, $classB] = $this->makeSchoolWithClassAndStudents('School B');

        Sanctum::actingAs($teacherA);

        // classB belongs to a different school entirely.
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $classB->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $studentsA[0]->id, 'status' => 'present']],
        ])->assertUnprocessable();
    }

    public function test_teacher_cannot_manage_roles_or_users(): void
    {
        [, , , , $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise');

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/roles')->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_classes_endpoint_lists_each_classs_subjects(): void
    {
        [$school, $class, , , $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise');
        $maths = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $class->subjects()->attach($maths->id);

        Sanctum::actingAs($teacher);
        $response = $this->getJson('/api/v1/attendance/classes')->assertOk();

        $classJson = collect($response->json('data'))->firstWhere('id', $class->id);
        $this->assertSame('Mathematics', collect($classJson['subjects'])->firstWhere('id', $maths->id)['name']);
    }

    public function test_subject_attendance_is_recorded_separately_from_class_attendance(): void
    {
        [$school, $class, $students, , $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise', 1);
        $maths = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $class->subjects()->attach($maths->id);

        Sanctum::actingAs($teacher);

        // Whole-day/class register: present.
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertOk();

        // Same student, same date, but a Mathematics-lesson register: absent.
        // Must NOT overwrite (or be overwritten by) the class-level row.
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'subject_id' => $maths->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'absent']],
        ])->assertOk();

        $this->assertSame(2, \App\Models\AttendanceRecord::where('student_id', $students[0]->id)->count());
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[0]->id, 'date' => '2026-02-01', 'subject_id' => null, 'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[0]->id, 'date' => '2026-02-01', 'subject_id' => $maths->id, 'status' => 'absent',
        ]);

        // The register endpoint, scoped to that subject, reflects only the
        // subject-level status — not the class-level one.
        $response = $this->getJson(
            "/api/v1/attendance?school_class_id={$class->id}&date=2026-02-01&subject_id={$maths->id}"
        )->assertOk();
        $this->assertSame('absent', collect($response->json('data.students'))->firstWhere('id', $students[0]->id)['status']);

        // Both registers are independently locked now — still just the two rows.
        $this->assertSame(2, \App\Models\AttendanceRecord::where('student_id', $students[0]->id)->count());
    }

    public function test_cannot_mark_attendance_for_a_subject_the_class_does_not_offer(): void
    {
        [$school, $class, $students, , $teacher] = $this->makeSchoolWithClassAndStudents('Sunrise', 1);
        $otherClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 2']);
        $chemistry = Subject::create(['school_id' => $school->id, 'name' => 'Chemistry', 'code' => 'CHEM']);
        $otherClass->subjects()->attach($chemistry->id); // linked to a different class, not this one

        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $class->id,
            'subject_id' => $chemistry->id,
            'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertUnprocessable();
    }
}
