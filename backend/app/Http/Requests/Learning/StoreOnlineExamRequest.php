<?php

namespace App\Http\Requests\Learning;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOnlineExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('online_exams.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'subject_id' => ['nullable', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            // graded = one official attempt, kept on the student's record.
            // practice = unlimited retakes, purely for self-review.
            'mode' => ['required', Rule::in(['graded', 'practice'])],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],

            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:1000'],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.choices' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.choices.*.choice_text' => ['required', 'string', 'max:255'],
            'questions.*.choices.*.is_correct' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('questions', []) as $i => $question) {
                $correctCount = collect($question['choices'] ?? [])
                    ->filter(fn ($c) => filter_var($c['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
                    ->count();

                if ($correctCount !== 1) {
                    $validator->errors()->add(
                        "questions.{$i}.choices",
                        __('Question :n needs exactly one correct choice marked.', ['n' => $i + 1])
                    );
                }
            }
        });
    }
}
