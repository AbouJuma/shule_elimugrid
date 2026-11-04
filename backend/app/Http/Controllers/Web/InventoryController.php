<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\StoreStockMovementRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\StockService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class InventoryController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('inventory.view');

        $items = InventoryItem::query()
            ->with('inventoryCategory')
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('inventory_category_id', $id))
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'reorder_level')->where('reorder_level', '>', 0))
            ->orderBy('name')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.inventory.index', [
            'items' => $items,
            'tab' => $request->string('tab')->toString() === 'categories' ? 'categories' : 'items',
            'categories' => InventoryCategory::query()->withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryItemRequest $request, TenantContext $tenant, StockService $stock)
    {
        $data = $request->validated();
        $openingQuantity = $data['opening_quantity'] ?? 0;
        unset($data['opening_quantity']);

        $item = InventoryItem::create(['school_id' => $tenant->id(), ...$data, 'category' => $this->categoryLabel($data)]);

        if ($openingQuantity > 0) {
            $stock->recordMovement($item, 'in', (float) $openingQuantity, 'Opening stock', $request->user());
        }

        return redirect()->route('school-admin.inventory.index')->with('status', __('":p1" added to inventory.', ['p1' => $item->name]));
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item)
    {
        $data = $request->validated();
        $item->update([...$data, 'category' => $this->categoryLabel($data)]);

        return redirect()->route('school-admin.inventory.index')->with('status', __('":p1" updated.', ['p1' => $item->name]));
    }

    public function destroy(InventoryItem $item)
    {
        $this->authorize('inventory.manage');

        if ($item->quantity_on_hand > 0) {
            return back()->withErrors(['item' => 'This item still has stock on hand — record it out first.']);
        }

        $item->delete();

        return redirect()->route('school-admin.inventory.index')->with('status', __('":p1" deleted.', ['p1' => $item->name]));
    }

    public function movements(Request $request, InventoryItem $item)
    {
        $this->authorize('inventory.view');

        return view('school-admin.inventory.movements', [
            'item' => $item,
            'movements' => $item->movements()->with('recordedBy')->latest('moved_at')->latest('id')
                ->paginate(PageSize::resolve($request))->withQueryString(),
        ]);
    }

    public function storeMovement(StoreStockMovementRequest $request, StockService $stock)
    {
        $data = $request->validated();
        $item = InventoryItem::findOrFail($data['inventory_item_id']);

        $stock->recordMovement(
            $item, $data['type'], (float) $data['quantity'], $data['reason'] ?? null,
            $request->user(), \Illuminate\Support\Carbon::parse($data['moved_at']),
        );

        return redirect()->route('school-admin.inventory.movements', $item)->with('status', 'Stock movement recorded.');
    }

    /** Keeps the legacy free-text column mirroring the chosen category's name. */
    protected function categoryLabel(array $data): ?string
    {
        return empty($data['inventory_category_id']) ? null : InventoryCategory::find($data['inventory_category_id'])?->name;
    }

    public function storeCategory(Request $request, TenantContext $tenant)
    {
        $this->authorize('inventory.manage');
        $schoolId = $tenant->assertBound()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('inventory_categories', 'name')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        InventoryCategory::create(['school_id' => $schoolId, ...$data]);

        return redirect()->route('school-admin.inventory.index', ['tab' => 'categories'])->with('status', __('Category ":p1" added.', ['p1' => $data['name']]));
    }

    public function updateCategory(Request $request, InventoryCategory $category, TenantContext $tenant)
    {
        $this->authorize('inventory.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('inventory_categories', 'name')->where('school_id', $tenant->assertBound()->id)->whereNull('deleted_at')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category->update($data);
        $category->items()->update(['category' => $data['name']]);

        return redirect()->route('school-admin.inventory.index', ['tab' => 'categories'])->with('status', 'Category updated.');
    }

    public function destroyCategory(InventoryCategory $category)
    {
        $this->authorize('inventory.manage');

        if ($category->items()->exists()) {
            return back()->withErrors(['category' => 'This category still has items — move or delete them first.']);
        }

        $category->forceDelete();

        return redirect()->route('school-admin.inventory.index', ['tab' => 'categories'])->with('status', 'Category deleted.');
    }
}
