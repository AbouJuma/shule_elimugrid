<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Accepts one or many rows in a single submission — the "add several
 * classes at once" form posts classes[0..n], but a single-row submission
 * validates identically.
 */
class StoreSchoolClassesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('classes.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'classes' => ['required', 'array', 'min:1'],
            'classes.*.name' => ['required', 'string', 'max:100', Rule::unique('school_classes', 'name')->where('school_id', $schoolId)],
            'classes.*.order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $names = collect($this->input('classes', []))->pluck('name')->filter()->map(fn ($n) => strtolower(trim($n)));

            $names->duplicates()->each(function ($name) use ($validator, $names) {
                $index = $names->search($name);
                $validator->errors()->add("classes.{$index}.name", "\"{$name}\" is repeated in this batch.");
            });
        });
    }
}
