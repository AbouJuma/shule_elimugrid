<?php

namespace Tests\Feature\Roles;

use App\Models\School;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Rolling a new default permission out to schools that already existed before it was added. */
class SyncTenantPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_the_configured_permission_to_every_schools_matching_role(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $a = School::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $b = School::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($a);
        app(DefaultRoleProvisioner::class)->provision($b);

        // 'transport.manage' isn't in a Driver's default permissions — a clean "this wasn't there before" case.
        setPermissionsTeamId($a->id);
        $this->assertFalse(Role::where('name', 'Driver')->where('school_id', $a->id)->first()->hasPermissionTo('transport.manage'));

        config(['roles.additive_grants' => ['transport.manage' => ['Driver']]]);
        Artisan::call('tenants:sync-permissions');

        foreach ([$a, $b] as $school) {
            setPermissionsTeamId($school->id);
            $this->assertTrue(Role::where('name', 'Driver')->where('school_id', $school->id)->first()->hasPermissionTo('transport.manage'));
        }
    }

    public function test_it_never_touches_a_permission_a_school_deliberately_removed(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $role = Role::where('name', 'Teacher')->where('school_id', $school->id)->first();
        $role->revokePermissionTo('exams.record_marks'); // the school deliberately took this away

        config(['roles.additive_grants' => ['classes.view' => ['Teacher']]]); // an unrelated addition
        Artisan::call('tenants:sync-permissions');

        setPermissionsTeamId($school->id);
        $this->assertFalse($role->fresh()->hasPermissionTo('exams.record_marks'), "the school's own customisation must survive a sync");
    }

    public function test_it_skips_a_school_that_renamed_or_removed_the_role_instead_of_erroring(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        Role::where('name', 'Storekeeper')->where('school_id', $school->id)->first()->delete();

        config(['roles.additive_grants' => ['inventory.view' => ['Storekeeper']]]);

        Artisan::call('tenants:sync-permissions');
        $this->assertStringContainsString('Checked 1 school', Artisan::output());
    }

    public function test_the_school_option_limits_it_to_one_school(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $a = School::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $b = School::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($a);
        app(DefaultRoleProvisioner::class)->provision($b);

        config(['roles.additive_grants' => ['transport.manage' => ['Driver']]]);
        Artisan::call('tenants:sync-permissions', ['--school' => $a->id]);

        setPermissionsTeamId($b->id);
        $this->assertFalse(Role::where('name', 'Driver')->where('school_id', $b->id)->first()->hasPermissionTo('transport.manage'));
    }
}
