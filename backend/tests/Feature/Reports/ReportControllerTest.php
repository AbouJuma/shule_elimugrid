<?php

namespace Tests\Feature\Reports;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeAdmin(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => 'Admin',
            'email' => 'admin@sunrise.test', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_admin_can_view_the_reports_landing_page(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->get('/reports')->assertOk()->assertSee('Enrollment');
    }

    public function test_enrollment_report_exports_csv_with_expected_headers(): void
    {
        [, $admin] = $this->makeAdmin();

        $response = $this->actingAs($admin)->get('/reports/enrollment?export=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Class,Total,Male,Female,Active', $response->streamedContent());
    }

    public function test_teacher_cannot_view_reports(): void
    {
        [$school] = $this->makeAdmin();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-reports@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/reports')->assertForbidden();
    }
}
