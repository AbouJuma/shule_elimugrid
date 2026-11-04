<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryItemCrudTest extends TestCase
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

    public function test_admin_can_update_and_delete_an_item_with_no_stock(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/inventory', ['name' => 'A4 Paper', 'unit' => 'ream', 'reorder_level' => 5]);
        $item = InventoryItem::first();

        $this->actingAs($admin)->put("/inventory/{$item->uuid}", [
            'name' => 'A4 Paper Ream', 'unit' => 'ream', 'reorder_level' => 10,
        ])->assertRedirect(route('school-admin.inventory.index'));
        $this->assertSame('A4 Paper Ream', $item->fresh()->name);

        $this->actingAs($admin)->delete("/inventory/{$item->uuid}")
            ->assertRedirect(route('school-admin.inventory.index'));
        $this->assertSoftDeleted($item);
    }

    public function test_deleting_an_item_with_stock_on_hand_is_blocked(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/inventory', ['name' => 'A4 Paper', 'unit' => 'ream', 'opening_quantity' => 10]);
        $item = InventoryItem::first();

        $this->actingAs($admin)->delete("/inventory/{$item->uuid}")->assertSessionHasErrors('item');
        $this->assertNotSoftDeleted($item);
    }
}
