<?php

namespace Tests\Feature\Academics;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AttendanceWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithClassAndStudents(string $name = 'Sunrise', int $count = 2): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        $students = collect(range(1, $count))->map(fn ($i) => Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-{$i}", 'first_name' => "S{$i}", 'last_name' => 'Test',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]));

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $class, $students, $admin];
    }

    public function test_register_page_renders_a_radio_button_per_status_option(): void
    {
        [, $class, , $admin] = $this->makeSchoolWithClassAndStudents();

        $response = $this->actingAs($admin)->get("/attendance?school_class_id={$class->id}&date=2026-02-01");

        $response->assertOk();
        $response->assertSee('type="radio"', false);
        $response->assertSee('Mark all Present', false);
        $response->assertSee('Mark all Absent', false);
    }

    public function test_admin_can_save_attendance_via_the_web_form(): void
    {
        [, $class, $students, $admin] = $this->makeSchoolWithClassAndStudents();

        $this->actingAs($admin)->post('/attendance', [
            'school_class_id' => $class->id,
            'date' => '2026-02-01',
            'records' => [
                ['student_id' => $students[0]->id, 'status' => 'present'],
                ['student_id' => $students[1]->id, 'status' => 'absent'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_records', ['student_id' => $students[0]->id, 'status' => 'present']);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $students[1]->id, 'status' => 'absent']);
        $this->assertSame(2, AttendanceRecord::count());
    }

    public function test_register_page_shows_a_by_subject_tab_per_subject_the_class_offers(): void
    {
        [$school, $class, , $admin] = $this->makeSchoolWithClassAndStudents();
        $maths = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $class->subjects()->attach($maths->id);

        $response = $this->actingAs($admin)->get("/attendance?school_class_id={$class->id}&date=2026-02-01");

        $response->assertOk();
        $response->assertSee('Mathematics', false);
    }

    public function test_admin_can_save_subject_level_attendance_via_the_web_form(): void
    {
        [$school, $class, $students, $admin] = $this->makeSchoolWithClassAndStudents();
        $maths = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $class->subjects()->attach($maths->id);

        $this->actingAs($admin)->post('/attendance', [
            'school_class_id' => $class->id,
            'subject_id' => $maths->id,
            'date' => '2026-02-01',
            'records' => [
                ['student_id' => $students[0]->id, 'status' => 'present'],
                ['student_id' => $students[1]->id, 'status' => 'absent'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[0]->id, 'subject_id' => $maths->id, 'status' => 'present',
        ]);
        $this->assertSame(2, AttendanceRecord::where('subject_id', $maths->id)->count());
    }

    public function test_a_saved_register_shows_locked_in_the_portal_and_can_only_be_edited_after_an_admin_unlocks_it(): void
    {
        [$school, $class, $students, $admin] = $this->makeSchoolWithClassAndStudents();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher',
            'email' => 'teacher@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->post('/attendance', [
            'school_class_id' => $class->id, 'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ])->assertRedirect();

        // The register page now shows it's locked, with no way to resave.
        $response = $this->actingAs($teacher)->get("/attendance?school_class_id={$class->id}&date=2026-02-01");
        $response->assertOk();
        $response->assertSee('Locked by', false);
        $response->assertDontSee('Save Attendance', false);

        // A teacher has no attendance.unlock permission, so resubmitting is refused...
        $this->actingAs($teacher)->post('/attendance', [
            'school_class_id' => $class->id, 'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'absent']],
        ])->assertSessionHasErrors();

        // ...but a School Administrator can unlock it, and then it's editable again.
        $this->actingAs($admin)->post('/attendance/unlock', [
            'school_class_id' => $class->id, 'date' => '2026-02-01',
        ])->assertRedirect();

        $this->actingAs($admin)->post('/attendance', [
            'school_class_id' => $class->id, 'date' => '2026-02-01',
            'records' => [['student_id' => $students[0]->id, 'status' => 'absent']],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $students[0]->id, 'date' => '2026-02-01', 'status' => 'absent',
        ]);
    }
}
