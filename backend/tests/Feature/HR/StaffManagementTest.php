<?php

namespace Tests\Feature\HR;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeAdmin(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_admin_can_onboard_a_staff_member_with_a_sequential_employee_number(): void
    {
        [$school, $admin] = $this->makeAdmin();

        $response = $this->actingAs($admin)->post('/staff', [
            'user' => ['name' => 'Grace Teacher', 'email' => 'grace@sunrise.test', 'phone' => '0700000000', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
        ]);

        $response->assertRedirect(route('school-admin.staff.index'));
        $response->assertSessionHas('status', fn ($status) => str_contains($status, 'EMP-00001'));

        $this->assertDatabaseHas('users', ['email' => 'grace@sunrise.test', 'school_id' => $school->id]);
        $this->assertDatabaseHas('staff_profiles', ['employee_number' => 'EMP-00001', 'employment_type' => 'full_time']);

        $user = User::where('email', 'grace@sunrise.test')->first();
        $this->assertTrue($user->hasRole('Teacher'));
    }

    public function test_employee_numbers_increment_sequentially_per_school(): void
    {
        [, $admin] = $this->makeAdmin();

        foreach (['a@sunrise.test' => '0700000001', 'b@sunrise.test' => '0700000002'] as $email => $phone) {
            $this->actingAs($admin)->post('/staff', [
                'user' => ['name' => 'Staff', 'email' => $email, 'phone' => $phone, 'role' => 'Teacher'],
                'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
            ]);
        }

        $this->assertDatabaseHas('staff_profiles', ['employee_number' => 'EMP-00001']);
        $this->assertDatabaseHas('staff_profiles', ['employee_number' => 'EMP-00002']);
    }

    public function test_admin_can_update_a_staff_profile(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/staff', [
            'user' => ['name' => 'Grace Teacher', 'email' => 'grace@sunrise.test', 'phone' => '0700000000', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
        ]);

        $profile = \App\Models\StaffProfile::first();

        $this->actingAs($admin)->patch("/staff/{$profile->uuid}", [
            'job_title' => 'Head of Mathematics',
            'employment_type' => 'full_time',
            'date_joined' => '2026-01-15',
        ])->assertRedirect(route('school-admin.staff.index'));

        $this->assertDatabaseHas('staff_profiles', ['id' => $profile->id, 'job_title' => 'Head of Mathematics']);
    }

    public function test_admin_can_rename_reemail_and_reassign_a_staff_members_role(): void
    {
        [$school, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/staff', [
            'user' => ['name' => 'Grace Teacher', 'email' => 'grace@sunrise.test', 'phone' => '0700000000', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
        ]);
        $profile = \App\Models\StaffProfile::first();
        $user = $profile->user;
        $this->assertTrue($user->hasRole('Teacher'));
        $this->assertFalse($user->hasRole('HR'));

        $this->actingAs($admin)->patch("/staff/{$profile->uuid}", [
            'name' => 'Grace Mwangi',
            'email' => 'grace.mwangi@sunrise.test',
            'role' => 'HR',
            'employment_type' => 'full_time',
            'date_joined' => '2026-01-15',
        ])->assertRedirect(route('school-admin.staff.index'));

        $user->refresh();
        $this->assertSame('Grace Mwangi', $user->name);
        $this->assertSame('grace.mwangi@sunrise.test', $user->email);
        setPermissionsTeamId($school->id);
        $this->assertTrue($user->fresh()->hasRole('HR'));
        $this->assertFalse($user->fresh()->hasRole('Teacher')); // reassigned, not just added to
    }

    public function test_an_admin_cannot_change_their_own_role(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/staff', [
            'user' => ['name' => $admin->name, 'email' => 'placeholder-not-used@sunrise.test', 'phone' => '0700000000', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
        ]);

        // The admin's own onboarding didn't go through /staff (School Administrators
        // are created at school onboarding) — give them a StaffProfile directly
        // so the update route has something to act on.
        $ownProfile = \App\Models\StaffProfile::create([
            'school_id' => $admin->school_id, 'user_id' => $admin->id, 'employee_number' => 'EMP-SELF',
            'employment_type' => 'full_time', 'date_joined' => '2026-01-15',
        ]);

        $this->actingAs($admin)->patch("/staff/{$ownProfile->uuid}", [
            'role' => 'Teacher',
            'employment_type' => 'full_time',
            'date_joined' => '2026-01-15',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->hasRole('School Administrator'));
    }

    public function test_admin_can_suspend_and_reactivate_staff(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/staff', [
            'user' => ['name' => 'Grace Teacher', 'email' => 'grace@sunrise.test', 'phone' => '0700000000', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
        ]);

        $user = User::where('email', 'grace@sunrise.test')->first();

        $this->actingAs($admin)->patch("/staff/{$user->uuid}/toggle-status")
            ->assertRedirect(route('school-admin.staff.index'));
        $this->assertSame('suspended', $user->fresh()->status);

        $this->actingAs($admin)->patch("/staff/{$user->uuid}/toggle-status");
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_teacher_cannot_view_or_onboard_staff(): void
    {
        [$school] = $this->makeAdmin();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-hr@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/staff')->assertForbidden();
        $this->actingAs($teacher)->post('/staff', [
            'user' => ['name' => 'X', 'email' => 'x@test.local', 'phone' => '0700000000', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
        ])->assertForbidden();
    }

    public function test_school_b_cannot_see_school_as_staff(): void
    {
        [$schoolA, $adminA] = $this->makeAdmin('School A');
        [, $adminB] = $this->makeAdmin('School B');

        $this->actingAs($adminA)->post('/staff', [
            'user' => ['name' => 'A Staff', 'email' => 'a-staff@test.local', 'phone' => '0700000000', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-15'],
        ]);

        // Flush the session flash ("A Staff onboarded...") left by the
        // request above — it's tied to the session, not the user, and
        // would otherwise "leak" into the next response regardless of
        // who's authenticated for it. Real browsers never share a
        // session across two different users' logins the way this test
        // client's cookie jar does by default.
        session()->flush();

        $response = $this->actingAs($adminB)->get('/staff');
        $response->assertOk();
        $response->assertDontSee('a-staff@test.local'); // the actual staff-table row content, not a flash banner
    }
}
