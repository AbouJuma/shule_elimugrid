<?php

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Spec section 7: "USER FROM SCHOOL A must NOT be able to access SCHOOL B
 * DATA" — this is called out as one of the most important security tests
 * in the whole system, so it gets its own dedicated suite rather than
 * being folded into a generic auth test.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithAdmin(string $name): array
    {
        $school = School::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => 'active',
        ]);

        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);

        $admin = User::create([
            'school_id' => $school->id,
            'name' => $name.' Admin',
            'email' => str($name)->slug().'-admin@test.local',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_school_admin_only_sees_users_from_their_own_school(): void
    {
        [$schoolA, $adminA] = $this->makeSchoolWithAdmin('School A');
        [$schoolB, $adminB] = $this->makeSchoolWithAdmin('School B');

        Sanctum::actingAs($adminA);
        $responseA = $this->getJson('/api/v1/users');
        $responseA->assertOk();
        $responseA->assertJsonCount(1, 'data');
        $responseA->assertJsonPath('data.0.email', $adminA->email);

        Sanctum::actingAs($adminB);
        $responseB = $this->getJson('/api/v1/users');
        $responseB->assertOk();
        $responseB->assertJsonCount(1, 'data');
        $responseB->assertJsonPath('data.0.email', $adminB->email);
    }

    public function test_school_admin_cannot_access_super_admin_routes(): void
    {
        [, $adminA] = $this->makeSchoolWithAdmin('School A');

        Sanctum::actingAs($adminA);
        $this->getJson('/api/v1/super-admin/schools')
            ->assertForbidden();
    }

    public function test_user_created_by_one_school_admin_is_invisible_to_another(): void
    {
        [$schoolA, $adminA] = $this->makeSchoolWithAdmin('School A');
        [$schoolB, $adminB] = $this->makeSchoolWithAdmin('School B');

        // IdentifyTenant middleware sets the Spatie team context from
        // $adminA's own school_id as part of the request — no manual setup
        // needed here, unlike in makeSchoolWithAdmin() which runs outside
        // any HTTP request.
        Sanctum::actingAs($adminA);
        $this->postJson('/api/v1/users', [
            'name' => 'Teacher A',
            'email' => 'teacher-a@test.local',
            'role' => 'Teacher',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'teacher-a@test.local',
            'school_id' => $schoolA->id,
        ]);

        Sanctum::actingAs($adminB);
        $responseB = $this->getJson('/api/v1/users');
        $responseB->assertJsonCount(1, 'data');
        $responseB->assertJsonMissingPath('data.1');
        collect($responseB->json('data'))->each(
            fn ($user) => $this->assertNotSame('teacher-a@test.local', $user['email'])
        );
    }

    public function test_suspended_school_users_are_blocked_at_login(): void
    {
        [$school, $admin] = $this->makeSchoolWithAdmin('Suspended School');
        $school->update(['status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'device_name' => 'test',
        ])->assertUnprocessable();
    }
}
