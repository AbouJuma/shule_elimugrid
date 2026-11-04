<?php

namespace App\Http\Requests\Transport;

use App\Http\Requests\Concerns\ResolvesUuidReferences;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStudentTransportRequest extends FormRequest
{
    use ResolvesUuidReferences;

    protected function prepareForValidation(): void
    {
        $this->resolveUuid('transport_route_id', 'transport_routes');
    }

    public function authorize(): bool
    {
        return $this->user()->can('transport.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'transport_route_id' => ['nullable', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'route_stop_id' => [
                'nullable',
                Rule::exists('route_stops', 'id')->where('transport_route_id', $this->input('transport_route_id')),
            ],
        ];
    }
}
