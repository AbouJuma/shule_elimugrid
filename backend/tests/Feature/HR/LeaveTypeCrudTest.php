<?php

namespace Tests\Feature\HR;

use App\Models\LeaveType;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveTypeCrudTest extends TestCase
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
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_admin_can_update_and_delete_a_leave_type(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/leave/types', ['name' => 'Annual Leave', 'days_per_year' => 21]);
        $type = LeaveType::first();

        $this->actingAs($admin)->put("/leave/types/{$type->uuid}", ['name' => 'Annual Leave (Revised)', 'days_per_year' => 24])
            ->assertRedirect(route('school-admin.leave.index'));
        $this->assertSame('Annual Leave (Revised)', $type->fresh()->name);

        $this->actingAs($admin)->delete("/leave/types/{$type->uuid}")
            ->assertRedirect(route('school-admin.leave.index'));
        $this->assertSoftDeleted($type);
    }

    public function test_deleting_a_leave_type_with_requests_is_blocked(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/leave/types', ['name' => 'Annual Leave', 'days_per_year' => 21]);
        $type = LeaveType::first();

        app(LeaveService::class)->apply($admin, $type, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-03'), null);

        $this->actingAs($admin)->delete("/leave/types/{$type->uuid}")->assertSessionHasErrors('leave_type');
        $this->assertNotSoftDeleted($type);
    }
}
