<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('exams.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150'],
            'weight' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'term_id' => ['nullable', Rule::exists('terms', 'id')->where('school_id', $schoolId)],
            // An exam can run across several classes at once (e.g. a
            // shared "Mid-Term" for Form 1 and Form 2).
            'school_class_ids' => ['required', 'array', 'min:1'],
            'school_class_ids.*' => [Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
