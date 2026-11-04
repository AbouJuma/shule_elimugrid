<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExamMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('exams.record_marks');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();
        $examId = $this->route('exam')?->id;
        $classId = $this->integer('school_class_id');

        return [
            // Which of the exam's classes this register of students is
            // for — an exam can span several, so this is never implied.
            'school_class_id' => ['required', Rule::exists('exam_classes', 'school_class_id')->where('exam_id', $examId)],
            'records' => ['required', 'array', 'min:1'],
            // Scoped to the chosen class, not just the school — a student
            // from a different class can't be slipped into this register.
            'records.*.student_id' => [
                'required',
                Rule::exists('students', 'id')->where('school_id', $schoolId)->where('school_class_id', $classId),
            ],
            // Upper bound (max_marks for this exam/subject) is enforced in
            // the controller, where the exam_subjects pivot row is
            // already loaded — no route parameter carries it here.
            'records.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
