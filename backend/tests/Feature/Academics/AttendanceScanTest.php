<?php

namespace Tests\Feature\Academics;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function scenario(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-0001', 'first_name' => 'Neema', 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        $receptionist = User::create(['school_id' => $school->id, 'name' => 'Front Desk', 'email' => str($name)->slug().'-fd@test.local', 'password' => 'password', 'status' => 'active']);
        $receptionist->assignRole('Receptionist');

        $teacherUser = User::create(['school_id' => $school->id, 'name' => 'Carol Teacher', 'email' => str($name)->slug().'-carol@test.local', 'password' => 'password', 'status' => 'active']);
        $teacherUser->assignRole('Teacher');
        StaffProfile::create(['school_id' => $school->id, 'user_id' => $teacherUser->id, 'employee_number' => 'EMP-00001', 'date_joined' => '2025-01-01']);

        $accountant = User::create(['school_id' => $school->id, 'name' => 'Accountant', 'email' => str($name)->slug().'-acc@test.local', 'password' => 'password', 'status' => 'active']);
        $accountant->assignRole('Accountant');

        return compact('school', 'class', 'student', 'admin', 'receptionist', 'teacherUser', 'accountant');
    }

    public function test_a_receptionist_scans_a_students_admission_number_and_marks_them_present(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['receptionist']);
        $response = $this->postJson('/api/v1/attendance/scan', ['code' => 'STU-0001'])->assertOk();

        $response->assertJsonPath('data.type', 'student');
        $response->assertJsonPath('data.name', 'Neema Test');
        $response->assertJsonPath('data.status', 'present');

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $d['student']->id, 'date' => now()->toDateString(), 'status' => 'present',
        ]);
    }

    public function test_scanning_a_staff_members_employee_number_marks_them_present(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['receptionist']);
        $response = $this->postJson('/api/v1/attendance/scan', ['code' => 'EMP-00001'])->assertOk();

        $response->assertJsonPath('data.type', 'staff');
        $response->assertJsonPath('data.name', 'Carol Teacher');

        $this->assertDatabaseHas('staff_attendance_records', [
            'user_id' => $d['teacherUser']->id, 'status' => 'present', 'source' => 'scan',
        ]);
    }

    public function test_scanning_bypasses_an_existing_register_lock(): void
    {
        $d = $this->scenario();

        // Lock today's staff register first.
        Sanctum::actingAs($d['admin']);
        $this->postJson('/api/v1/staff-attendance', [
            'date' => now()->toDateString(),
            'records' => [['user_id' => $d['teacherUser']->id, 'status' => 'absent']],
        ])->assertOk();

        // A scan for the SAME person on the SAME (now locked) date still succeeds.
        Sanctum::actingAs($d['receptionist']);
        $this->postJson('/api/v1/attendance/scan', ['code' => 'EMP-00001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'present');

        $this->assertDatabaseHas('staff_attendance_records', ['user_id' => $d['teacherUser']->id, 'status' => 'present', 'source' => 'scan']);
    }

    public function test_an_unrecognised_code_is_reported_clearly(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['receptionist']);
        $this->postJson('/api/v1/attendance/scan', ['code' => 'NOT-A-REAL-CODE'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => "No student or staff member found for 'NOT-A-REAL-CODE'."]);
    }

    public function test_an_accountant_cannot_use_the_scanner(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['accountant']);
        $this->postJson('/api/v1/attendance/scan', ['code' => 'STU-0001'])->assertForbidden();
    }

    public function test_scanning_the_same_student_twice_reports_already_marked(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['receptionist']);
        $this->postJson('/api/v1/attendance/scan', ['code' => 'STU-0001'])
            ->assertOk()->assertJsonPath('data.already_marked', false);

        $this->postJson('/api/v1/attendance/scan', ['code' => 'STU-0001'])
            ->assertOk()->assertJsonPath('data.already_marked', true);
    }
}
