<?php

namespace Tests\Feature\SuperAdmin;

use App\Mail\SchoolOnboardedMail;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Onboarding a new school: the admin actually gets emailed their login, and a mail failure never undoes it. */
class SchoolOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        $role = Role::findOrCreate('Super Admin', 'web');
        $role->syncPermissions(Permission::all());

        $admin = User::create(['school_id' => null, 'name' => 'Platform Admin', 'email' => 'platform-admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole($role);

        return $admin;
    }

    protected function payload(): array
    {
        return [
            'school' => ['name' => 'Green Valley School', 'email' => 'info@greenvalley.test', 'phone' => '0712000000'],
            'admin' => ['name' => 'Grace Mwangi', 'email' => 'grace@greenvalley.test', 'phone' => '0722000000'],
        ];
    }

    public function test_onboarding_a_school_emails_the_new_administrator_their_login_and_password(): void
    {
        Mail::fake();

        $this->actingAs($this->makeSuperAdmin())->post('/super-admin/schools', $this->payload())
            ->assertRedirect(route('super-admin.dashboard'));

        $school = School::where('name', 'Green Valley School')->firstOrFail();
        $admin = User::where('email', 'grace@greenvalley.test')->firstOrFail();

        Mail::assertSent(SchoolOnboardedMail::class, function ($mail) use ($school, $admin) {
            return $mail->hasTo($admin->email)
                && $mail->school->is($school)
                && $mail->admin->is($admin)
                && strlen($mail->temporaryPassword) >= 10;
        });
    }

    public function test_the_email_actually_renders_the_school_name_login_and_password(): void
    {
        $school = School::create(['name' => 'Green Valley School', 'slug' => 'green-valley', 'status' => 'active']);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Grace Mwangi', 'email' => 'grace@greenvalley.test', 'password' => 'x', 'status' => 'active']);

        $mail = new SchoolOnboardedMail($school, $admin, 'Zx81QwErTy55');
        $rendered = $mail->render();

        $this->assertStringContainsString('Green Valley School', $rendered);
        $this->assertStringContainsString('Grace Mwangi', $rendered);
        $this->assertStringContainsString('grace@greenvalley.test', $rendered);
        $this->assertStringContainsString('Zx81QwErTy55', $rendered);
        $this->assertSame('Green Valley School is ready on ElimuGrid', $mail->envelope()->subject);
    }

    public function test_a_mail_failure_never_undoes_a_successful_onboarding(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));

        $result = app(SchoolOnboardingService::class)->onboard($this->payload());

        $this->assertTrue($result['school']->exists);
        $this->assertDatabaseHas('schools', ['name' => 'Green Valley School']);
        $this->assertDatabaseHas('users', ['email' => 'grace@greenvalley.test']);
        $this->assertNotEmpty($result['temporary_password'], 'Super Admin still gets it back even when the email fails');
    }

    public function test_the_api_onboarding_endpoint_also_sends_the_email(): void
    {
        Mail::fake();

        $this->actingAs($this->makeSuperAdmin())->postJson('/api/v1/super-admin/schools', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.school.name', 'Green Valley School')
            ->assertJsonStructure(['data' => ['temporary_password']]);

        Mail::assertSent(SchoolOnboardedMail::class);
    }
}
