<?php

namespace App\Http\Requests\Transport;

use App\Http\Requests\Concerns\ResolvesUuidReferences;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    use ResolvesUuidReferences;

    protected function prepareForValidation(): void
    {
        $this->resolveUuid('driver_user_id', 'users');
    }

    public function authorize(): bool
    {
        return $this->user()->can('transport.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'registration_number' => [
                'required', 'string', 'max:30',
                Rule::unique('vehicles', 'registration_number')->where('school_id', $schoolId),
            ],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'maintenance', 'inactive'])],
            'driver_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
