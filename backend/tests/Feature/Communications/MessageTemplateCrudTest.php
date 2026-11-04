<?php

namespace Tests\Feature\Communications;

use App\Models\MessageTemplate;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MessageTemplateCrudTest extends TestCase
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

    public function test_admin_can_update_and_delete_a_message_template(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/communications/templates', [
            'name' => 'Fee Reminder', 'category' => 'fee_reminder', 'body' => 'Dear parent, fees are due.',
        ]);
        $template = MessageTemplate::first();

        $this->actingAs($admin)->put("/communications/templates/{$template->uuid}", [
            'name' => 'Fee Reminder v2', 'category' => 'fee_reminder', 'body' => 'Dear parent, fees are overdue.',
        ])->assertRedirect(route('school-admin.communications.index'));
        $this->assertSame('Fee Reminder v2', $template->fresh()->name);

        $this->actingAs($admin)->delete("/communications/templates/{$template->uuid}")
            ->assertRedirect(route('school-admin.communications.index'));
        $this->assertSoftDeleted($template);
    }
}
