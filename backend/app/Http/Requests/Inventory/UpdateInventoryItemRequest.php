<?php

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deliberately excludes quantity_on_hand — StockService::recordMovement()
 * is the only place that column is ever written, so it never appears as
 * an editable field here (same invariant as the store request's
 * opening_quantity comment explains).
 */
class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('inventory_items', 'name')->where('school_id', $schoolId)->ignore($this->route('item')),
            ],
            'inventory_category_id' => ['nullable', Rule::exists('inventory_categories', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
