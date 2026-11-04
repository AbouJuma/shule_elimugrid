<?php

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dynamic, per-school roles (spec section 8) reuse Spatie's teams feature,
 * but Role has no automatic tenant scope of its own — route model binding
 * resolves ANY role by id regardless of which school owns it. These tests
 * exist because that gap is easy to reintroduce silently (e.g. a future
 * controller method that forgets the ownership check).
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolAdmin(string $schoolName): array
    {
        $school = School::create(['name' => $schoolName, 'slug' => str($schoolName)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id,
            'name' => $schoolName.' Admin',
            'email' => str($schoolName)->slug().'-admin@test.local',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    protected function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        $role = Role::findOrCreate('Super Admin', 'web');
        $role->syncPermissions(Permission::all());

        $admin = User::create([
            'school_id' => null,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@test.local',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole($role);

        return $admin;
    }

    public function test_school_admin_can_create_and_list_a_custom_role(): void
    {
        [, $adminA] = $this->makeSchoolAdmin('School A');

        Sanctum::actingAs($adminA);

        $this->postJson('/api/v1/roles', [
            'name' => 'Exam Coordinator',
            'permissions' => ['users.view'],
        ])->assertCreated()->assertJsonPath('data.name', 'Exam Coordinator');

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Exam Coordinator']);
    }

    public function test_school_admin_cannot_view_update_or_delete_another_schools_role(): void
    {
        [$schoolA, $adminA] = $this->makeSchoolAdmin('School A');
        [$schoolB, $adminB] = $this->makeSchoolAdmin('School B');

        setPermissionsTeamId($schoolB->id);
        $foreignRole = Role::findOrCreate('Bursar', 'web');

        Sanctum::actingAs($adminA);

        $this->getJson("/api/v1/roles/{$foreignRole->id}")->assertNotFound();
        $this->patchJson("/api/v1/roles/{$foreignRole->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/api/v1/roles/{$foreignRole->id}")->assertNotFound();

        $this->assertDatabaseHas('roles', ['id' => $foreignRole->id, 'name' => 'Bursar']);
    }

    public function test_deleting_a_role_still_assigned_to_users_is_blocked(): void
    {
        [$school, $admin] = $this->makeSchoolAdmin('School A');

        setPermissionsTeamId($school->id);
        $adminRole = Role::findByName('School Administrator', 'web');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/roles/{$adminRole->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    /**
     * The critical regression case: Super Admin's own platform role has
     * school_id = null. Without an explicit guard, a platform user's
     * TenantContext id is also null, so a naive `where('school_id', null)`
     * query would resolve to the Super Admin role itself.
     */
    public function test_super_admin_cannot_manage_roles_through_the_school_scoped_endpoint(): void
    {
        $platformAdmin = $this->makeSuperAdmin();
        Sanctum::actingAs($platformAdmin);

        $this->getJson('/api/v1/roles')->assertForbidden();
        $this->postJson('/api/v1/roles', ['name' => 'Should Not Exist'])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Should Not Exist']);
    }
}
