<?php

namespace Tests\Feature\Academics;

use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        $hr = User::create(['school_id' => $school->id, 'name' => 'HR Person', 'email' => str($name)->slug().'-hr@test.local', 'password' => 'password', 'status' => 'active']);
        $hr->assignRole('HR');

        $teacherUser = User::create(['school_id' => $school->id, 'name' => 'Carol Teacher', 'email' => str($name)->slug().'-carol@test.local', 'password' => 'password', 'status' => 'active']);
        $teacherUser->assignRole('Teacher');
        StaffProfile::create(['school_id' => $school->id, 'user_id' => $teacherUser->id, 'employee_number' => 'EMP-00001', 'date_joined' => '2025-01-01']);

        return [$school, $admin, $hr, $teacherUser];
    }

    public function test_hr_marks_staff_attendance_and_it_locks_until_an_admin_unlocks_it(): void
    {
        [$school, $admin, $hr, $teacherUser] = $this->makeSchool();

        Sanctum::actingAs($hr);
        $this->postJson('/api/v1/staff-attendance', [
            'date' => '2026-02-01',
            'records' => [['user_id' => $teacherUser->id, 'status' => 'present']],
        ])->assertOk();

        $this->assertDatabaseHas('staff_attendance_records', [
            'user_id' => $teacherUser->id, 'date' => '2026-02-01', 'status' => 'present', 'source' => 'register',
        ]);

        // Locked — resubmitting is refused even by HR.
        $this->postJson('/api/v1/staff-attendance', [
            'date' => '2026-02-01',
            'records' => [['user_id' => $teacherUser->id, 'status' => 'absent']],
        ])->assertUnprocessable();

        // HR cannot unlock — only School Administrator can.
        $this->postJson('/api/v1/staff-attendance/unlock', ['date' => '2026-02-01'])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/staff-attendance/unlock', ['date' => '2026-02-01'])->assertOk();

        Sanctum::actingAs($hr);
        $this->postJson('/api/v1/staff-attendance', [
            'date' => '2026-02-01',
            'records' => [['user_id' => $teacherUser->id, 'status' => 'absent']],
        ])->assertOk();

        $this->assertSame(1, \App\Models\StaffAttendanceRecord::where('user_id', $teacherUser->id)->count());
        $this->assertDatabaseHas('staff_attendance_records', ['user_id' => $teacherUser->id, 'status' => 'absent']);
    }

    public function test_a_teacher_cannot_mark_staff_attendance(): void
    {
        [$school, , , $teacherUser] = $this->makeSchool();

        Sanctum::actingAs($teacherUser);
        $this->postJson('/api/v1/staff-attendance', [
            'date' => '2026-02-01',
            'records' => [['user_id' => $teacherUser->id, 'status' => 'present']],
        ])->assertForbidden();
    }

    public function test_the_register_lists_only_active_staff(): void
    {
        [$school, $admin, , $teacherUser] = $this->makeSchool();

        $departedUser = User::create(['school_id' => $school->id, 'name' => 'Left Already', 'email' => 'left@test.local', 'password' => 'password', 'status' => 'suspended']);
        StaffProfile::create(['school_id' => $school->id, 'user_id' => $departedUser->id, 'employee_number' => 'EMP-00002', 'date_joined' => '2024-01-01', 'date_left' => '2026-01-01']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/staff-attendance?date=2026-02-01')->assertOk();

        $names = collect($response->json('data.staff'))->pluck('name');
        $this->assertContains('Carol Teacher', $names);
        $this->assertNotContains('Left Already', $names);
    }
}
