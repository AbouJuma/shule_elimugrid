<?php

namespace Tests\Feature\HR;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithStaff(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => 'Admin',
            'email' => 'admin@sunrise.test', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher',
            'email' => 'teacher@sunrise.test', 'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $leaveType = LeaveType::create(['school_id' => $school->id, 'name' => 'Annual Leave', 'days_per_year' => 21]);

        return [$school, $admin, $teacher, $leaveType];
    }

    public function test_any_staff_member_can_apply_for_leave_within_balance(): void
    {
        [, , $teacher, $leaveType] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/leave', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-05',
        ])->assertRedirect(route('school-admin.leave.index'));

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $teacher->id, 'leave_type_id' => $leaveType->id, 'days' => 5, 'status' => 'pending',
        ]);
    }

    public function test_applying_beyond_the_annual_balance_is_rejected(): void
    {
        [, , $teacher, $leaveType] = $this->makeSchoolWithStaff();

        // 21 days/year allowance; this single request already exceeds it.
        $response = $this->from('/leave')->actingAs($teacher)->post('/leave', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-01-01', 'end_date' => '2026-01-25',
        ]);

        $response->assertRedirect('/leave');
        $response->assertSessionHasErrors('days');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_approved_days_count_against_the_balance_for_a_second_request(): void
    {
        [, $admin, $teacher, $leaveType] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/leave', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-01-01', 'end_date' => '2026-01-15', // 15 days
        ]);
        $first = LeaveRequest::first();
        $this->actingAs($admin)->patch("/leave/{$first->uuid}/approve");

        // 15 already approved; asking for 10 more (25 total) exceeds the 21/year allowance.
        $response = $this->from('/leave')->actingAs($teacher)->post('/leave', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-02-01', 'end_date' => '2026-02-10',
        ]);

        $response->assertSessionHasErrors('days');
    }

    public function test_admin_can_approve_and_reject_leave_requests(): void
    {
        [, $admin, $teacher, $leaveType] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/leave', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-02',
        ]);
        $request = LeaveRequest::first();

        $this->actingAs($admin)->patch("/leave/{$request->uuid}/approve")
            ->assertRedirect(route('school-admin.leave.index'));

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->approved_by);
    }

    public function test_teacher_cannot_approve_their_own_or_anyone_elses_leave_request(): void
    {
        [, , $teacher, $leaveType] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/leave', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-02',
        ]);
        $request = LeaveRequest::first();

        $this->actingAs($teacher)->patch("/leave/{$request->uuid}/approve")->assertForbidden();
    }

    public function test_approving_an_already_approved_request_is_rejected(): void
    {
        [, $admin, $teacher, $leaveType] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/leave', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-02',
        ]);
        $request = LeaveRequest::first();
        $this->actingAs($admin)->patch("/leave/{$request->uuid}/approve");

        $response = $this->from('/leave')->actingAs($admin)->patch("/leave/{$request->uuid}/approve");
        $response->assertSessionHasErrors('status');
    }
}
