<?php

namespace Tests\Feature\Account;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Changing your own password from the portal — open to every logged-in user, no permission needed. */
class ChangePasswordWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeUser(string $role = 'Teacher'): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $user = User::create([
            'school_id' => $school->id, 'name' => 'Grace Teacher', 'email' => 'grace@sunrise.test',
            'password' => '0712345678', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return [$school, $user];
    }

    public function test_a_staff_member_can_change_their_first_password_phone_number_to_their_own_choice(): void
    {
        [, $user] = $this->makeUser();

        $this->actingAs($user)->get('/account/password')->assertOk()->assertSee('first time signing in', false);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => '0712345678',
            'password' => 'MyOwnSecret1',
            'password_confirmation' => 'MyOwnSecret1',
        ])->assertRedirect(route('school-admin.account.password.edit'));

        $this->assertTrue(Hash::check('MyOwnSecret1', $user->fresh()->password));
    }

    public function test_the_current_password_must_actually_match(): void
    {
        [, $user] = $this->makeUser();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong-one',
            'password' => 'MyOwnSecret1',
            'password_confirmation' => 'MyOwnSecret1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('0712345678', $user->fresh()->password), 'the password must be unchanged');
    }

    public function test_the_new_password_must_be_confirmed_and_different_from_the_current_one(): void
    {
        [, $user] = $this->makeUser();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => '0712345678',
            'password' => 'MyOwnSecret1',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put('/account/password', [
            'current_password' => '0712345678',
            'password' => '0712345678',
            'password_confirmation' => '0712345678',
        ])->assertSessionHasErrors('password');
    }

    public function test_any_role_can_reach_the_page_no_permission_required(): void
    {
        [, $accountant] = $this->makeUser('Accountant');

        $this->actingAs($accountant)->get('/account/password')->assertOk();
    }
}
