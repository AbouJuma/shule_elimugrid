<?php

namespace App\Http\Requests\Fees;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateInvoicesRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ResolvesUuidReferences;

    protected function prepareForValidation(): void
    {
        $this->resolveUuidList('student_ids', 'students');
    }

    public function authorize(): bool
    {
        return $this->user()->can('fees.invoice');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            // Omit student_ids entirely to invoice every active student in
            // the fee structure's class.
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => [Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('school_id', $schoolId)],
            'notify' => ['sometimes', 'boolean'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
