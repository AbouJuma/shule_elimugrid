<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeAdmin(string $name = 'Sunrise'): array
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

    public function test_creating_an_item_with_opening_quantity_records_a_stock_in_movement(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/inventory', [
            'name' => 'A4 Paper Ream', 'unit' => 'ream', 'reorder_level' => 5, 'opening_quantity' => 20,
        ])->assertRedirect(route('school-admin.inventory.index'));

        $item = InventoryItem::where('name', 'A4 Paper Ream')->first();
        $this->assertEquals(20, $item->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id, 'type' => 'in', 'quantity' => 20, 'reason' => 'Opening stock',
        ]);
    }

    public function test_stock_in_and_stock_out_movements_adjust_quantity_on_hand(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/inventory', ['name' => 'Chalk Box', 'unit' => 'box', 'opening_quantity' => 10]);
        $item = InventoryItem::where('name', 'Chalk Box')->first();

        $this->actingAs($admin)->post('/inventory/movements', [
            'inventory_item_id' => $item->id, 'type' => 'in', 'quantity' => 5, 'moved_at' => now()->toDateString(),
        ]);
        $this->assertEquals(15, $item->fresh()->quantity_on_hand);

        $this->actingAs($admin)->post('/inventory/movements', [
            'inventory_item_id' => $item->id, 'type' => 'out', 'quantity' => 8, 'reason' => 'Issued to Form 1', 'moved_at' => now()->toDateString(),
        ]);
        $this->assertEquals(7, $item->fresh()->quantity_on_hand);
    }

    public function test_stock_out_exceeding_quantity_on_hand_is_rejected(): void
    {
        [, $admin] = $this->makeAdmin();

        $this->actingAs($admin)->post('/inventory', ['name' => 'Marker Pens', 'unit' => 'pcs', 'opening_quantity' => 3]);
        $item = InventoryItem::where('name', 'Marker Pens')->first();

        $response = $this->from('/inventory')->actingAs($admin)->post('/inventory/movements', [
            'inventory_item_id' => $item->id, 'type' => 'out', 'quantity' => 10, 'moved_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertEquals(3, $item->fresh()->quantity_on_hand);
    }

    public function test_teacher_cannot_manage_inventory(): void
    {
        [$school] = $this->makeAdmin();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-inv@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->post('/inventory', ['name' => 'X', 'unit' => 'pcs'])->assertForbidden();
    }

    public function test_school_b_cannot_see_school_as_inventory_items(): void
    {
        [, $adminA] = $this->makeAdmin('School A');
        [, $adminB] = $this->makeAdmin('School B');

        $this->actingAs($adminA)->post('/inventory', ['name' => 'Only In A', 'unit' => 'pcs']);
        session()->flush();

        $response = $this->actingAs($adminB)->get('/inventory');
        $response->assertOk();
        $response->assertDontSee('Only In A');
    }
}
