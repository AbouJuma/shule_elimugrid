<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.mark');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            // Absent = the whole-day/class register; present = a per-subject
            // one — must be a subject actually offered by this class (the
            // class_subject pivot), not just any subject in the school.
            'subject_id' => [
                'nullable',
                Rule::exists('class_subject', 'subject_id')->where('school_class_id', $this->input('school_class_id')),
            ],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => [
                'required',
                Rule::exists('students', 'id')->where('school_id', $schoolId)->where('school_class_id', $this->input('school_class_id')),
            ],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
