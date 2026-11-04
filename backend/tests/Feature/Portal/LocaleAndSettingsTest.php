<?php

namespace Tests\Feature\Portal;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocaleAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeAdmin(string $name = 'Sunrise', string $role = 'School Administrator'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $user = User::create([
            'school_id' => $school->id, 'name' => "{$name} User",
            'email' => str($name)->slug().'-'.str($role)->slug().'@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return [$school, $user];
    }

    public function test_pages_default_to_english(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Academics')->assertDontSee('Taaluma');
    }

    public function test_switching_to_swahili_translates_the_portal_and_is_remembered(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/language/sw')->assertRedirect();
        $this->assertSame('sw', $admin->fresh()->locale);

        $this->actingAs($admin)->get('/subjects')
            ->assertOk()
            ->assertSee('Taaluma')          // sidebar group
            ->assertSee('Masomo')           // page title
            ->assertSee('Somo Jipya')       // button
            ->assertSee('lang="sw"', false);
    }

    public function test_a_saved_preference_applies_on_a_fresh_session(): void
    {
        [, $admin] = $this->makeAdmin();
        $admin->forceFill(['locale' => 'sw'])->save();

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Taaluma');
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/language/fr')->assertNotFound();
        $this->assertNull($admin->fresh()->locale);
    }

    public function test_static_flash_messages_and_validation_errors_are_translated(): void
    {
        [, $admin] = $this->makeAdmin();
        $admin->forceFill(['locale' => 'sw'])->save();

        $this->actingAs($admin)->post('/academics/classes', ['classes' => [['name' => 'Form 1']]]);
        $this->actingAs($admin)->delete('/academics/classes/'.\App\Models\SchoolClass::first()->id);
        $this->actingAs($admin)->get('/academics?tab=classes')->assertOk()->assertSee('Darasa limefutwa.');
    }

    public function test_dynamic_flash_messages_keep_their_values_when_translated(): void
    {
        [, $admin] = $this->makeAdmin();
        $admin->forceFill(['locale' => 'sw'])->save();

        $this->actingAs($admin)->post('/subjects', ['name' => 'Kemia'])->assertSessionHas('status', 'Somo "Kemia" limeundwa.');

        $admin->forceFill(['locale' => 'en'])->save();
        $this->actingAs($admin)->post('/subjects', ['name' => 'Fizikia'])->assertSessionHas('status', 'Subject "Fizikia" created.');
    }
    public function test_admin_can_update_school_details(): void
    {
        [$school, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->put('/settings/school', [
            'name' => 'Sunrise Academy', 'registration_number' => 'REG/123', 'email' => 'info@sunrise.test',
            'phone' => '0712345678', 'website' => 'https://sunrise.test', 'motto' => 'Aim high',
            'address' => 'Box 1, Dar es Salaam', 'timezone' => 'Africa/Dar_es_Salaam',
        ])->assertRedirect(route('school-admin.settings.school'));

        $school->refresh();
        $this->assertSame('Sunrise Academy', $school->name);
        $this->assertSame('Aim high', $school->motto);
        $this->assertSame('sunrise', $school->slug); // platform-controlled, untouched
        $this->assertSame('active', $school->status);
    }

    public function test_school_details_require_a_name_and_valid_timezone(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->from('/settings/school')->actingAs($admin)->put('/settings/school', ['name' => '', 'timezone' => 'Mars/Base'])
            ->assertSessionHasErrors(['name', 'timezone']);
    }

    public function test_logo_can_be_uploaded_served_and_replaced(): void
    {
        Storage::fake('public');
        [$school, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/settings/school/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertRedirect(route('school-admin.settings.school'));

        $first = $school->fresh()->logo_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($admin)->get('/school-logo')->assertOk();

        $this->actingAs($admin)->post('/settings/school/logo', ['logo' => UploadedFile::fake()->image('new.jpg', 100, 100)]);
        $second = $school->fresh()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($admin)->delete('/settings/school/logo');
        $this->assertNull($school->fresh()->logo_path);
        $this->actingAs($admin)->get('/school-logo')->assertNotFound();
    }

    public function test_non_image_logo_is_rejected(): void
    {
        Storage::fake('public');
        [, $admin] = $this->makeAdmin();

        $this->from('/settings/school')->actingAs($admin)
            ->post('/settings/school/logo', ['logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('logo');
    }

    public function test_only_school_managers_can_edit_the_profile(): void
    {
        [, $teacher] = $this->makeAdmin('Sunrise', 'Teacher');

        $this->actingAs($teacher)->get('/settings/school')->assertForbidden();
        $this->actingAs($teacher)->put('/settings/school', ['name' => 'Hacked', 'timezone' => 'Africa/Dar_es_Salaam'])->assertForbidden();
    }

    public function test_each_school_only_sees_its_own_logo(): void
    {
        Storage::fake('public');
        [$schoolA, $adminA] = $this->makeAdmin('School A');
        [, $adminB] = $this->makeAdmin('School B');

        $this->actingAs($adminA)->post('/settings/school/logo', ['logo' => UploadedFile::fake()->image('a.png')]);
        $this->assertNotNull($schoolA->fresh()->logo_path);

        $this->actingAs($adminB)->get('/school-logo')->assertNotFound();
    }
}
