<?php

namespace App\Http\Requests\IdCards;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueIdCardRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ResolvesUuidReferences;

    protected function prepareForValidation(): void
    {
        $this->resolveUuid('student_id', 'students');
    }

    public function authorize(): bool
    {
        return $this->user()->can('id_cards.issue');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'id_card_template_id' => ['nullable', Rule::exists('id_card_templates', 'id')->where('school_id', $schoolId)],
            'issued_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
