<?php

namespace App\Http\Requests\Transport;

use App\Http\Requests\Concerns\ResolvesUuidReferences;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransportRouteRequest extends FormRequest
{
    use ResolvesUuidReferences;

    protected function prepareForValidation(): void
    {
        $this->resolveUuid('vehicle_id', 'vehicles');
    }

    public function authorize(): bool
    {
        return $this->user()->can('transport.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('transport_routes', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($this->route('transport_route')),
            ],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('school_id', $schoolId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],

            'stops' => ['nullable', 'array'],
            // Existing stops are sent back with their id so editing a route keeps them (and the
            // students assigned to them) instead of recreating every stop.
            'stops.*.id' => ['nullable', 'integer'],
            'stops.*.name' => ['required', 'string', 'max:150'],
            'stops.*.sequence' => ['required', 'integer', 'min:1'],
            'stops.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'stops.*.pickup_time' => ['nullable', 'date_format:H:i'],
            'stops.*.dropoff_time' => ['nullable', 'date_format:H:i'],
        ];
    }
}
