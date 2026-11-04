<?php

namespace App\Http\Requests\Certificates;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificate_templates.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('certificate_templates', 'name')->where('school_id', $schoolId)],
            'type' => ['required', 'string', 'max:50'],
            'layout' => ['sometimes', Rule::in(array_keys(config('template_layouts.certificate')))],
            'orientation' => ['sometimes', Rule::in(['landscape', 'portrait'])],
            'primary_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_text' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
