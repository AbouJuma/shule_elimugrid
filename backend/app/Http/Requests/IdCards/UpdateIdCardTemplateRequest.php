<?php

namespace App\Http\Requests\IdCards;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIdCardTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('id_card_templates.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('id_card_templates', 'name')->where('school_id', $schoolId)->ignore($this->route('idCardTemplate')),
            ],
            'layout' => ['sometimes', Rule::in(array_keys(config('template_layouts.id_card')))],
            'orientation' => ['required', Rule::in(['landscape', 'portrait'])],
            'photo_position' => ['required', Rule::in(['left', 'right', 'center'])],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_text' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
