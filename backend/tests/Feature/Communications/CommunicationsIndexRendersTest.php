<?php

namespace Tests\Feature\Communications;

use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** The portal page actually renders (catches a Blade edit that only breaks at runtime). */
class CommunicationsIndexRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_communications_page_renders_with_the_automatic_messages_card(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        $this->actingAs($admin)->get('/communications')
            ->assertOk()
            ->assertSee('Automatic messages')
            ->assertSee('Fees reminder')
            ->assertSee('Login details');
    }
}
