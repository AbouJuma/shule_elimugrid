<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('schools.create');
    }

    public function rules(): array
    {
        return [
            'school' => ['required', 'array'],
            'school.name' => ['required', 'string', 'max:255'],
            'school.registration_number' => ['nullable', 'string', 'max:100'],
            'school.email' => ['nullable', 'email', 'max:255'],
            'school.phone' => ['nullable', 'string', 'max:30'],
            'school.website' => ['nullable', 'url', 'max:255'],
            'school.address' => ['nullable', 'string', 'max:1000'],
            'school.timezone' => ['nullable', 'string', 'max:64'],
            'school.currency' => ['nullable', 'string', 'size:3'],

            'admin' => ['required', 'array'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin.phone' => ['nullable', 'string', 'max:30'],

            'subscription_package_id' => ['nullable', 'integer', 'exists:subscription_packages,id'],
        ];
    }
}
