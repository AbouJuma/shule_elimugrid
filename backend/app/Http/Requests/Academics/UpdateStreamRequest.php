<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('classes.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();
        $stream = $this->route('stream');

        return [
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('streams', 'name')->where('school_class_id', $this->input('school_class_id'))->ignore($stream),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
