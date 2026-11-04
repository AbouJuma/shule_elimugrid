<?php

namespace App\Http\Requests\Communications;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('communications.manage_templates');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();
        $template = $this->route('messageTemplate');

        // A system message keeps its name, event and language — the school edits only the wording.
        if ($template?->isSystem()) {
            return ['body' => ['required', 'string', 'max:1000']];
        }

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('message_templates', 'name')->where('school_id', $schoolId)->ignore($this->route('messageTemplate'))],
            'category' => ['required', Rule::in(['general', 'fee_reminder', 'attendance', 'exam_result'])],
            'locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales')))],
            'body' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $template = $this->route('messageTemplate');
            $missing = collect(config('message_templates.required_tokens.'.$template?->event, []))
                ->reject(fn ($token) => str_contains((string) $this->input('body'), '{'.$token.'}'));

            if ($missing->isNotEmpty()) {
                $validator->errors()->add('body', __('The message must keep :tokens, or it will not do its job.', ['tokens' => $missing->map(fn ($t) => '{'.$t.'}')->implode(', ')]));
            }
        });
    }
}
