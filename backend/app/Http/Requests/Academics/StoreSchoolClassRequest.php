<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('classes.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('school_classes', 'name')->where('school_id', $schoolId)],
            'order' => ['sometimes', 'integer', 'min:0'],
            'campus_id' => ['nullable', Rule::exists('campuses', 'id')->where('school_id', $schoolId)],
        ];
    }
}
