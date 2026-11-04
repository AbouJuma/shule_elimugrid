<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\School;
use App\Models\Student;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The Super Admin dashboard: platform-wide numbers across every tenant, and suspending/activating a school. */
class PlatformDashboardTest extends TestCase
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

    /** Two schools, some students in one, one suspended already, on two different packages. */
    protected function seedPlatform(): array
    {
        $gold = SubscriptionPackage::create(['name' => 'Gold', 'slug' => 'gold', 'price' => 500000, 'billing_cycle' => 'annually']);
        $silver = SubscriptionPackage::create(['name' => 'Silver', 'slug' => 'silver', 'price' => 250000, 'billing_cycle' => 'annually']);

        $active = School::create(['name' => 'Sunrise Academy', 'slug' => 'sunrise', 'status' => 'active', 'subscription_package_id' => $gold->id]);
        $suspended = School::create(['name' => 'Twilight School', 'slug' => 'twilight', 'status' => 'suspended', 'subscription_package_id' => $silver->id]);
        app(DefaultRoleProvisioner::class)->provision($active);
        app(DefaultRoleProvisioner::class)->provision($suspended);

        $year = \App\Models\AcademicYear::create(['school_id' => $active->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = \App\Models\SchoolClass::create(['school_id' => $active->id, 'name' => 'Form 1']);
        foreach (range(1, 3) as $i) {
            Student::create(['school_id' => $active->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => "STU-{$i}", 'first_name' => "S{$i}", 'last_name' => 'Test', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15']);
        }

        setPermissionsTeamId($active->id);
        $schoolAdmin = User::create(['school_id' => $active->id, 'name' => 'School Admin', 'email' => 'schooladmin@test.local', 'password' => 'password', 'status' => 'active']);
        $schoolAdmin->assignRole('School Administrator');
        $teacher = User::create(['school_id' => $active->id, 'name' => 'Teacher', 'email' => 'teacher@test.local', 'password' => 'password', 'status' => 'active']);
        $teacher->assignRole('Teacher');
        $parent = User::create(['school_id' => $active->id, 'name' => 'Parent', 'email' => 'parent@test.local', 'password' => 'password', 'status' => 'active']);
        $parent->assignRole('Parent');

        return compact('active', 'suspended', 'gold', 'silver', 'schoolAdmin');
    }

    public function test_the_dashboard_counts_across_every_tenant_and_excludes_parents_from_staff(): void
    {
        $d = $this->seedPlatform();

        $r = $this->actingAs($this->makeSuperAdmin())->get('/super-admin/dashboard')->assertOk();

        $r->assertSee('Sunrise Academy')->assertSee('Twilight School');
        $r->assertViewHas('platform', function ($platform) {
            return $platform['stats']['total_schools'] === 2
                && $platform['stats']['active_schools'] === 1
                && $platform['stats']['suspended_schools'] === 1
                && $platform['stats']['total_students'] === 3
                // The School Administrator and the Teacher — the Parent login is excluded.
                && $platform['stats']['total_staff'] === 2;
        });
    }

    public function test_the_schools_table_shows_each_schools_own_active_student_count(): void
    {
        $d = $this->seedPlatform();

        $this->actingAs($this->makeSuperAdmin())->get('/super-admin/dashboard')
            ->assertOk()
            ->assertViewHas('schools', fn ($schools) => $schools->firstWhere('id', $d['active']->id)->students_count === 3
                && $schools->firstWhere('id', $d['suspended']->id)->students_count === 0);
    }

    public function test_suspending_and_activating_a_school_actually_blocks_and_restores_its_login(): void
    {
        $d = $this->seedPlatform();
        $schoolAdmin = $d['schoolAdmin'];

        $superAdmin = $this->makeSuperAdmin();

        $this->postJson('/api/v1/auth/login', ['email' => $schoolAdmin->email, 'password' => 'password', 'device_name' => 't'])->assertOk();

        $this->actingAs($superAdmin)->patch("/super-admin/schools/{$d['active']->uuid}/suspend")->assertRedirect('/super-admin/dashboard');
        $this->assertSame('suspended', $d['active']->fresh()->status);

        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => $schoolAdmin->email, 'password' => 'password', 'device_name' => 't'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        Sanctum::actingAs($superAdmin);
        $this->patchJson("/super-admin/schools/{$d['active']->uuid}/activate")->assertRedirect('/super-admin/dashboard');
        $this->assertSame('active', $d['active']->fresh()->status);

        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => $schoolAdmin->email, 'password' => 'password', 'device_name' => 't'])->assertOk();
    }

    public function test_a_school_administrator_cannot_suspend_or_activate_any_school(): void
    {
        $d = $this->seedPlatform();

        $this->actingAs($d['schoolAdmin'])->patch("/super-admin/schools/{$d['suspended']->uuid}/activate")->assertForbidden();
    }
}
