<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The same platform dashboard, for the app: GET /api/v1/super-admin/dashboard. */
class PlatformDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        $role = Role::findOrCreate('Super Admin', 'web');
        $role->syncPermissions(Permission::all());

        $admin = User::create(['school_id' => null, 'name' => 'Platform Admin', 'email' => 'platform-admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole($role);

        return $admin;
    }

    public function test_it_returns_platform_wide_stats_and_the_schools_list(): void
    {
        $active = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        $suspended = School::create(['name' => 'Twilight', 'slug' => 'twilight', 'status' => 'suspended']);
        app(DefaultRoleProvisioner::class)->provision($active);
        app(DefaultRoleProvisioner::class)->provision($suspended);

        Sanctum::actingAs($this->makeSuperAdmin());

        $r = $this->getJson('/api/v1/super-admin/dashboard')->assertOk();

        $r->assertJsonPath('data.stats.total_schools', 2)
            ->assertJsonPath('data.stats.active_schools', 1)
            ->assertJsonPath('data.stats.suspended_schools', 1)
            ->assertJsonCount(6, 'data.onboarded_by_month')
            ->assertJsonCount(2, 'data.recent_schools');
    }

    public function test_a_school_administrator_cannot_reach_it(): void
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);
        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/super-admin/dashboard')->assertForbidden();
    }

    public function test_the_schools_list_shows_active_student_counts_per_school(): void
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);
        $year = \App\Models\AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = \App\Models\SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        \App\Models\Student::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'STU-1', 'first_name' => 'A', 'last_name' => 'B', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15']);

        Sanctum::actingAs($this->makeSuperAdmin());

        $this->getJson('/api/v1/super-admin/schools')->assertOk()->assertJsonPath('data.0.students_count', 1);
    }
}
