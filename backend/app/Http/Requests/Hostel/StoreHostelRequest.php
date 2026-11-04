<?php

namespace App\Http\Requests\Hostel;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostelRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ResolvesUuidReferences;

    protected function prepareForValidation(): void
    {
        $this->resolveUuid('warden_user_id', 'users');
    }

    public function authorize(): bool
    {
        return $this->user()->can('hostel.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('hostels', 'name')->where('school_id', $schoolId)],
            'gender' => ['required', Rule::in(['male', 'female', 'mixed'])],
            'warden_user_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $schoolId)],
        ];
    }
}
