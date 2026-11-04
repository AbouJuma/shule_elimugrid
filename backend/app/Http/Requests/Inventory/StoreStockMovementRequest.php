<?php

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('school_id', $schoolId)],
            'type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'moved_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
