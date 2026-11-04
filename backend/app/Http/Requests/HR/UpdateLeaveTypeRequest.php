<?php

namespace App\Http\Requests\HR;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('leave_types', 'name')->where('school_id', $schoolId)->ignore($this->route('leaveType')),
            ],
            'days_per_year' => ['required', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['sometimes', 'boolean'],
        ];
    }
}
