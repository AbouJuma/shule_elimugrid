<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeAdmin(string $name = 'Sunrise'): User
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return $admin;
    }

    public function test_categories_can_be_created_renamed_and_deleted(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'Stationery', 'description' => 'Paper and pens'])
            ->assertRedirect(route('school-admin.inventory.index', ['tab' => 'categories']));
        $category = InventoryCategory::first();

        $this->actingAs($admin)->put("/inventory/categories/{$category->uuid}", ['name' => 'Office Supplies'])->assertRedirect();
        $this->assertSame('Office Supplies', $category->fresh()->name);

        $this->actingAs($admin)->delete("/inventory/categories/{$category->uuid}")->assertRedirect();
        $this->assertDatabaseCount('inventory_categories', 0);
    }

    public function test_duplicate_category_names_are_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'Stationery']);

        $this->from('/inventory?tab=categories')->actingAs($admin)->post('/inventory/categories', ['name' => 'Stationery'])
            ->assertSessionHasErrors('name');
    }

    public function test_an_item_is_created_in_a_category_chosen_from_the_dropdown_and_can_be_filtered(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'Stationery']);
        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'Cleaning']);
        $stationery = InventoryCategory::where('name', 'Stationery')->first();
        $cleaning = InventoryCategory::where('name', 'Cleaning')->first();

        $this->actingAs($admin)->post('/inventory', ['name' => 'A4 Paper', 'unit' => 'ream', 'inventory_category_id' => $stationery->id]);
        $this->actingAs($admin)->post('/inventory', ['name' => 'Bleach', 'unit' => 'litre', 'inventory_category_id' => $cleaning->id]);

        $paper = InventoryItem::where('name', 'A4 Paper')->first();
        $this->assertSame($stationery->id, $paper->inventory_category_id);
        $this->assertSame('Stationery', $paper->categoryName());

        session()->flush();
        $this->actingAs($admin)->get("/inventory?category_id={$stationery->id}")
            ->assertOk()->assertSee('A4 Paper')->assertDontSee('Bleach');

        $this->actingAs($admin)->get('/inventory?tab=categories')->assertOk()->assertSee('Stationery')->assertSee('Cleaning');
    }

    public function test_renaming_a_category_updates_its_items(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'Stationery']);
        $category = InventoryCategory::first();
        $this->actingAs($admin)->post('/inventory', ['name' => 'A4 Paper', 'unit' => 'ream', 'inventory_category_id' => $category->id]);

        $this->actingAs($admin)->put("/inventory/categories/{$category->uuid}", ['name' => 'Office']);

        $this->assertSame('Office', InventoryItem::first()->categoryName());
    }

    public function test_a_category_with_items_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'Stationery']);
        $category = InventoryCategory::first();
        $this->actingAs($admin)->post('/inventory', ['name' => 'A4 Paper', 'unit' => 'ream', 'inventory_category_id' => $category->id]);

        $this->actingAs($admin)->delete("/inventory/categories/{$category->uuid}")->assertSessionHasErrors('category');
        $this->assertNotSoftDeleted($category);
    }

    public function test_an_item_cannot_use_another_schools_category(): void
    {
        $adminA = $this->makeAdmin('School A');
        $adminB = $this->makeAdmin('School B');
        $this->actingAs($adminA)->post('/inventory/categories', ['name' => 'Private']);
        $foreign = InventoryCategory::withoutGlobalScopes()->first();

        $this->from('/inventory')->actingAs($adminB)->post('/inventory', [
            'name' => 'Sneaky', 'unit' => 'pcs', 'inventory_category_id' => $foreign->id,
        ])->assertSessionHasErrors('inventory_category_id');
    }
}
