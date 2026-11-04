<?php

namespace App\Http\Requests\Admissions;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The full admission workflow (spec section 14): student details plus one
 * or more guardians, each either an existing Guardian (guardian_id) or
 * new contact details to create one inline.
 */
class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('students.create');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'student' => ['required', 'array'],
            'student.first_name' => ['required', 'string', 'max:255'],
            'student.last_name' => ['required', 'string', 'max:255'],
            'student.gender' => ['required', Rule::in(['male', 'female'])],
            'student.date_of_birth' => ['required', 'date', 'before:today'],
            'student.admission_date' => ['required', 'date'],
            'student.academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'student.school_class_id' => ['nullable', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'student.stream_id' => ['nullable', Rule::exists('streams', 'id')->where('school_id', $schoolId)],
            'student.campus_id' => ['nullable', Rule::exists('campuses', 'id')->where('school_id', $schoolId)],

            'notify_guardian' => ['sometimes', 'boolean'],

            'guardians' => ['required', 'array', 'min:1'],
            'guardians.*.guardian_id' => ['nullable', Rule::exists('guardians', 'id')->where('school_id', $schoolId)],
            'guardians.*.name' => ['required_without:guardians.*.guardian_id', 'nullable', 'string', 'max:255'],
            'guardians.*.phone' => ['required_without:guardians.*.guardian_id', 'nullable', 'string', 'max:30'],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:255'],
            'guardians.*.address' => ['nullable', 'string', 'max:1000'],
            'guardians.*.locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales')))],
            'guardians.*.relationship' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'guardians.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $schoolClassId = $this->input('student.school_class_id');
            $streamId = $this->input('student.stream_id');

            if ($streamId && $schoolClassId) {
                $belongs = \App\Models\Stream::where('id', $streamId)
                    ->where('school_class_id', $schoolClassId)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('student.stream_id', 'The selected stream does not belong to the selected class.');
                }
            }
        });
    }
}
