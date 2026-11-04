<?php

namespace App\Http\Requests\Communications;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('communications.manage_templates');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('message_templates', 'name')->where('school_id', $schoolId)],
            'category' => ['required', Rule::in(['general', 'fee_reminder', 'attendance', 'exam_result'])],
            'locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales')))],
            'body' => ['required', 'string', 'max:1000'],
        ];
    }
}
