<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\StoreStockMovementRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\StockService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Stock items, categories and movements for the mobile app. */
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('inventory.view');

        $items = InventoryItem::query()
            ->with('inventoryCategory')
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('inventory_category_id', $id))
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'reorder_level')->where('reorder_level', '>', 0))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $items->getCollection()->map(fn ($i) => $this->resource($i))->values(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    public function store(StoreInventoryItemRequest $request, TenantContext $tenant, StockService $stock)
    {
        $data = $request->validated();
        $opening = $data['opening_quantity'] ?? 0;
        unset($data['opening_quantity']);

        $item = InventoryItem::create(['school_id' => $tenant->id(), ...$data, 'category' => $this->categoryLabel($data)]);

        if ($opening > 0) {
            $stock->recordMovement($item, 'in', (float) $opening, 'Opening stock', $request->user());
        }

        return response()->json(['data' => $this->resource($item->fresh('inventoryCategory'))], 201);
    }

    public function update(UpdateInventoryItemRequest $request, int $item)
    {
        $model = InventoryItem::query()->findOrFail($item);
        $data = $request->validated();
        $model->update([...$data, 'category' => $this->categoryLabel($data)]);

        return response()->json(['data' => $this->resource($model->fresh('inventoryCategory'))]);
    }

    public function destroy(int $item)
    {
        $this->authorize('inventory.manage');
        $model = InventoryItem::query()->findOrFail($item);

        if ($model->quantity_on_hand > 0) {
            return response()->json(['message' => __('This item still has stock on hand — record it out first.'), 'errors' => ['item' => [__('This item still has stock on hand — record it out first.')]]], 422);
        }

        $model->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function categories()
    {
        $this->authorize('inventory.view');

        return response()->json(['data' => InventoryCategory::query()->withCount('items')->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'items_count' => $c->items_count,
        ])->values()]);
    }

    public function storeCategory(Request $request, TenantContext $tenant)
    {
        $this->authorize('inventory.manage');
        $schoolId = $tenant->assertBound()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category = InventoryCategory::create(['school_id' => $schoolId, ...$data]);

        return response()->json(['data' => ['id' => $category->id, 'name' => $category->name, 'description' => $category->description, 'items_count' => 0]], 201);
    }

    public function movements(Request $request, int $item)
    {
        $this->authorize('inventory.view');
        $model = InventoryItem::query()->findOrFail($item);

        return response()->json(['data' => $model->movements()->with('recordedBy')->latest('moved_at')->latest('id')->limit(100)->get()->map(fn ($m) => [
            'id' => $m->id, 'type' => $m->type, 'quantity' => (string) $m->quantity, 'reason' => $m->reason,
            'moved_at' => $m->moved_at?->toDateString(), 'recorded_by' => $m->recordedBy?->name,
        ])->values()]);
    }

    public function storeMovement(StoreStockMovementRequest $request, StockService $stock)
    {
        $data = $request->validated();
        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);

        $stock->recordMovement($item, $data['type'], (float) $data['quantity'], $data['reason'] ?? null, $request->user(), Carbon::parse($data['moved_at']));

        return response()->json(['data' => $this->resource($item->fresh('inventoryCategory'))], 201);
    }

    protected function categoryLabel(array $data): ?string
    {
        return empty($data['inventory_category_id']) ? null : InventoryCategory::find($data['inventory_category_id'])?->name;
    }

    protected function resource(InventoryItem $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'category_id' => $i->inventory_category_id,
            'category' => $i->inventoryCategory?->name ?? $i->category,
            'unit' => $i->unit,
            'quantity_on_hand' => (string) $i->quantity_on_hand,
            'reorder_level' => $i->reorder_level !== null ? (string) $i->reorder_level : null,
            'unit_cost' => $i->unit_cost !== null ? (string) $i->unit_cost : null,
            'low_stock' => $i->reorder_level > 0 && $i->quantity_on_hand <= $i->reorder_level,
        ];
    }
}
