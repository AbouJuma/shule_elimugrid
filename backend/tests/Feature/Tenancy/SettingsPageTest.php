<?php

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** The combined Roles + "who holds which role" page (see App\Http\Controllers\Web\SettingsController). */
class SettingsPageTest extends TestCase
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
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_the_settings_page_shows_roles_and_staff_with_their_role(): void
    {
        [$school, $admin] = $this->makeAdmin();
        $teacher = User::create(['school_id' => $school->id, 'name' => 'Grace Teacher', 'email' => 'grace@test.local', 'password' => 'password', 'status' => 'active']);
        $teacher->assignRole('Teacher');
        StaffProfile::create(['school_id' => $school->id, 'user_id' => $teacher->id, 'employee_number' => 'EMP-1', 'employment_type' => 'full_time', 'date_joined' => '2026-01-15']);

        $this->actingAs($admin)->get('/settings')
            ->assertOk()
            ->assertSee('Roles & Permissions')
            ->assertSee('Teacher')
            ->assertSee('Grace Teacher')
            ->assertSee('School Administrator');
    }

    public function test_changing_a_staff_members_role_from_the_settings_page_works(): void
    {
        [$school, $admin] = $this->makeAdmin();
        $teacher = User::create(['school_id' => $school->id, 'name' => 'Grace Teacher', 'email' => 'grace@test.local', 'password' => 'password', 'status' => 'active']);
        $teacher->assignRole('Teacher');
        $profile = StaffProfile::create(['school_id' => $school->id, 'user_id' => $teacher->id, 'employee_number' => 'EMP-1', 'employment_type' => 'full_time', 'date_joined' => '2026-01-15']);

        $this->actingAs($admin)->patch("/staff/{$profile->uuid}", [
            'role' => 'HR', 'employment_type' => $profile->employment_type, 'date_joined' => $profile->date_joined->toDateString(),
        ])->assertRedirect(route('school-admin.staff.index'));

        $this->assertTrue($teacher->fresh()->hasRole('HR'));
        $this->assertFalse($teacher->fresh()->hasRole('Teacher'));
    }

    public function test_someone_without_roles_view_cannot_reach_settings(): void
    {
        [, $admin] = $this->makeAdmin();
        $school = $admin->school;
        $teacher = User::create(['school_id' => $school->id, 'name' => 'Grace Teacher', 'email' => 'grace@test.local', 'password' => 'password', 'status' => 'active']);
        setPermissionsTeamId($school->id);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/settings')->assertForbidden();
    }
}
