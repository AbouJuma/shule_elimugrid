<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('subjects.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subjects', 'name')->where('school_id', $schoolId)->ignore($this->route('subject')),
            ],
            'code' => ['nullable', 'string', 'max:20'],
            'school_class_ids' => ['sometimes', 'array'],
            'school_class_ids.*' => [Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
        ];
    }
}
