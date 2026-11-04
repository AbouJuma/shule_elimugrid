<?php

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * No permission gate on authorize() beyond "is logged in" — like leave
 * requests, submitting a purchase request is a floor capability every
 * staff member has, not something restricted to inventory.manage.
 */
class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', Rule::exists('inventory_items', 'id')->where('school_id', $schoolId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
