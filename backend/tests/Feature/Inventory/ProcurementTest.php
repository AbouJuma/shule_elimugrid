<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithStaff(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => 'Admin',
            'email' => 'admin@sunrise.test', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher',
            'email' => 'teacher@sunrise.test', 'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $item = InventoryItem::create(['school_id' => $school->id, 'name' => 'Chalk Box', 'unit' => 'box']);

        return [$school, $admin, $teacher, $item];
    }

    public function test_any_staff_member_can_submit_a_purchase_request_with_multiple_items(): void
    {
        [, , $teacher, $item] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/procurement', [
            'notes' => 'Needed for term start',
            'items' => [
                ['inventory_item_id' => $item->id, 'description' => 'Chalk Box', 'quantity' => 10, 'estimated_cost' => 5000],
                ['description' => 'New stapler brand', 'quantity' => 2],
            ],
        ])->assertRedirect(route('school-admin.procurement.index'));

        $request = PurchaseRequest::first();
        $this->assertSame('pending', $request->status);
        $this->assertSame($teacher->id, $request->requested_by);
        $this->assertCount(2, $request->items);
    }

    public function test_full_lifecycle_approve_then_receive_updates_stock_for_matched_items_only(): void
    {
        [, $admin, $teacher, $item] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/procurement', [
            'items' => [
                ['inventory_item_id' => $item->id, 'description' => 'Chalk Box', 'quantity' => 10],
                ['description' => 'Untracked item', 'quantity' => 3], // no inventory_item_id — not in catalog
            ],
        ]);
        $request = PurchaseRequest::first();

        $this->actingAs($admin)->patch("/procurement/{$request->uuid}/approve")
            ->assertRedirect(route('school-admin.procurement.index'));
        $this->assertSame('approved', $request->fresh()->status);

        $this->actingAs($admin)->patch("/procurement/{$request->uuid}/receive")
            ->assertRedirect(route('school-admin.procurement.index'));

        $this->assertSame('received', $request->fresh()->status);
        $this->assertEquals(10, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id, 'type' => 'in', 'quantity' => 10, 'purchase_request_id' => $request->id,
        ]);
        // Only one movement — the untracked line item never touched stock.
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_receiving_a_request_that_was_never_approved_is_rejected(): void
    {
        [, $admin, $teacher, $item] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/procurement', [
            'items' => [['inventory_item_id' => $item->id, 'description' => 'Chalk Box', 'quantity' => 10]],
        ]);
        $request = PurchaseRequest::first();

        $response = $this->from('/procurement')->actingAs($admin)->patch("/procurement/{$request->uuid}/receive");
        $response->assertSessionHasErrors('status');
        $this->assertEquals(0, $item->fresh()->quantity_on_hand);
    }

    public function test_rejected_requests_never_touch_stock(): void
    {
        [, $admin, $teacher, $item] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/procurement', [
            'items' => [['inventory_item_id' => $item->id, 'description' => 'Chalk Box', 'quantity' => 10]],
        ]);
        $request = PurchaseRequest::first();

        $this->actingAs($admin)->patch("/procurement/{$request->uuid}/reject");

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertEquals(0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_teacher_cannot_approve_a_purchase_request(): void
    {
        [, , $teacher, $item] = $this->makeSchoolWithStaff();

        $this->actingAs($teacher)->post('/procurement', [
            'items' => [['inventory_item_id' => $item->id, 'description' => 'Chalk Box', 'quantity' => 10]],
        ]);
        $request = PurchaseRequest::first();

        $this->actingAs($teacher)->patch("/procurement/{$request->uuid}/approve")->assertForbidden();
    }
}
