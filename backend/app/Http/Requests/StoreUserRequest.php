<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            // Must be one of THIS school's own roles — without this, a
            // client could pass any role name that happens to exist
            // globally (e.g. another school's custom role name) and,
            // since Role::findOrCreate() would silently create a new
            // team-scoped row rather than reuse it, still end up
            // assigning something unintended.
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('school_id', $schoolId)->where('guard_name', 'web'),
            ],
        ];
    }
}
