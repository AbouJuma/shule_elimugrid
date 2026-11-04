<?php

namespace App\Http\Requests\Communications;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('communications.send');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'recipient_type' => ['required', Rule::in(['student', 'class'])],
            'student_id' => [
                'required_if:recipient_type,student',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'school_class_id' => [
                'required_if:recipient_type,class',
                Rule::exists('school_classes', 'id')->where('school_id', $schoolId),
            ],
            'message' => ['required', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:50'],
        ];
    }
}
