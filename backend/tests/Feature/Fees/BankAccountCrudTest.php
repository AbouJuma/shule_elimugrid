<?php

namespace Tests\Feature\Fees;

use App\Models\BankAccount;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BankAccountCrudTest extends TestCase
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

    public function test_admin_can_update_and_delete_a_bank_account(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/bank-accounts', [
            'bank_name' => 'CRDB', 'account_name' => 'School Fees', 'account_number' => '123456', 'currency' => 'TZS',
        ]);
        $account = BankAccount::first();

        $this->actingAs($admin)->put("/bank-accounts/{$account->id}", [
            'bank_name' => 'NMB', 'account_name' => 'School Fees', 'account_number' => '654321', 'currency' => 'TZS', 'is_active' => '1',
        ])->assertRedirect(route('school-admin.bank-accounts.index'));
        $this->assertSame('NMB', $account->fresh()->bank_name);
        $this->assertTrue($account->fresh()->is_active);

        $this->actingAs($admin)->delete("/bank-accounts/{$account->id}")
            ->assertRedirect(route('school-admin.bank-accounts.index'));
        $this->assertSoftDeleted($account);
    }
}
