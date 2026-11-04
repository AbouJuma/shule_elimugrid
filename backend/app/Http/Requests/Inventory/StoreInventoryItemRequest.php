<?php

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('inventory_items', 'name')->where('school_id', $schoolId)],
            'inventory_category_id' => ['nullable', Rule::exists('inventory_categories', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            // Optional starting balance — becomes an "Opening stock"
            // movement rather than being written to quantity_on_hand
            // directly, so the movement ledger still explains every unit.
            'opening_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
