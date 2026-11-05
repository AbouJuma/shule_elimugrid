<?php

namespace Tests\Feature\Portal;

use App\Models\Guardian;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolAdmin(): User
    {
        $school = School::create(['name' => 'Portal School', 'slug' => 'portal-school', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id,
            'name' => 'Portal Admin',
            'email' => 'portal-admin@test.local',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return $admin;
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

    public function test_school_admin_can_login_and_reach_their_dashboard(): void
    {
        $admin = $this->makeSchoolAdmin();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);

        $this->get('/dashboard')->assertOk()->assertSee('Portal Admin');
    }

    public function test_a_parent_only_account_cannot_sign_into_the_staff_portal(): void
    {
        $school = School::create(['name' => 'Portal School', 'slug' => 'portal-school', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $parentUser = User::create([
            'school_id' => $school->id, 'name' => 'Mother', 'email' => 'mother@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $parentUser->assignRole('Parent');
        Guardian::create(['school_id' => $school->id, 'user_id' => $parentUser->id, 'name' => 'Mother', 'phone' => '0700000000']);

        $response = $this->post('/login', ['email' => 'mother@test.local', 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** A staff member who is ALSO a guardian of their own child must not be locked out of the portal they actually work in. */
    public function test_a_staff_member_who_also_holds_the_parent_role_can_still_sign_into_the_portal(): void
    {
        $school = School::create(['name' => 'Portal School', 'slug' => 'portal-school', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $teacherParent = User::create([
            'school_id' => $school->id, 'name' => 'Teacher Mother', 'email' => 'teacher-mother@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $teacherParent->assignRole(['Teacher', 'Parent']);

        $response = $this->post('/login', ['email' => 'teacher-mother@test.local', 'password' => 'password']);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($teacherParent);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = $this->makeSchoolAdmin();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_school_admin_cannot_reach_super_admin_dashboard(): void
    {
        $admin = $this->makeSchoolAdmin();
        $this->actingAs($admin);

        $this->get('/super-admin/dashboard')->assertForbidden();
    }

    public function test_super_admin_can_reach_platform_dashboard_and_create_a_school(): void
    {
        $platformAdmin = $this->makeSuperAdmin();
        $this->actingAs($platformAdmin);

        $this->get('/super-admin/dashboard')->assertOk();

        $response = $this->post('/super-admin/schools', [
            'school' => ['name' => 'New Web School'],
            'admin' => ['name' => 'Web Created Admin', 'email' => 'web-admin@test.local'],
        ]);

        $response->assertRedirect('/super-admin/dashboard');
        $this->assertDatabaseHas('schools', ['name' => 'New Web School']);
        $this->assertDatabaseHas('users', ['email' => 'web-admin@test.local']);
    }
}
