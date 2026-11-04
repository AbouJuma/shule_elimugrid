<?php

namespace App\Http\Requests\Admissions;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Changing status (e.g. to withdrawn/graduated) is gated by the
        // more specific students.archive permission; everything else by
        // students.update. See StudentController::update() for the
        // per-field enforcement — this is just the coarse "can they call
        // this endpoint at all" gate.
        return $this->user()->can('students.update') || $this->user()->can('students.archive');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', Rule::in(['male', 'female'])],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'school_class_id' => ['nullable', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'stream_id' => ['nullable', Rule::exists('streams', 'id')->where('school_id', $schoolId)],
            'campus_id' => ['nullable', Rule::exists('campuses', 'id')->where('school_id', $schoolId)],
            'status' => ['sometimes', Rule::in(['active', 'graduated', 'withdrawn', 'transferred', 'suspended'])],
            'reason' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }
}
