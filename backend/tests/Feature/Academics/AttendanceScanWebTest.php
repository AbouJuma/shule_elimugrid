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
use Tests\TestCase;

class AttendanceScanWebTest extends TestCase
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
        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-0001', 'first_name' => 'Neema', 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');
        $teacherUser = User::create(['school_id' => $school->id, 'name' => 'Carol Teacher', 'email' => 'carol@test.local', 'password' => 'password', 'status' => 'active']);
        $teacherUser->assignRole('Teacher');
        StaffProfile::create(['school_id' => $school->id, 'user_id' => $teacherUser->id, 'employee_number' => 'EMP-00001', 'date_joined' => '2025-01-01']);

        return compact('school', 'admin', 'student', 'teacherUser');
    }

    public function test_the_scan_page_and_staff_attendance_page_render_for_an_admin(): void
    {
        $d = $this->scenario();

        $this->actingAs($d['admin'])->get('/attendance-scan')->assertOk()->assertSee('Scan or type a code', false);
        $this->actingAs($d['admin'])->get('/staff-attendance')->assertOk()->assertSee('Carol Teacher', false);
    }

    public function test_scanning_via_the_portal_page_marks_the_student_present(): void
    {
        $d = $this->scenario();

        $response = $this->actingAs($d['admin'])->postJson('/attendance-scan', ['code' => 'STU-0001']);
        $response->assertOk()->assertJsonPath('data.name', 'Neema Test');

        $this->assertDatabaseHas('attendance_records', ['student_id' => $d['student']->id, 'status' => 'present']);
    }

    public function test_the_student_and_staff_qr_endpoints_return_a_scannable_svg(): void
    {
        $d = $this->scenario();

        $studentQr = $this->actingAs($d['admin'])->get(route('school-admin.students.qr', $d['student']));
        $studentQr->assertOk();
        $this->assertStringContainsString('svg', $studentQr->headers->get('Content-Type'));

        $staffQr = $this->actingAs($d['admin'])->get(route('school-admin.staff.qr', $d['teacherUser']));
        $staffQr->assertOk();
        $this->assertStringContainsString('svg', $staffQr->headers->get('Content-Type'));
    }
}
