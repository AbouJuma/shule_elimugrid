<?php

namespace App\Http\Requests\Academics;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStreamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('classes.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'streams' => ['required', 'array', 'min:1'],
            'streams.*.school_class_id' => ['required', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'streams.*.name' => ['required', 'string', 'max:100'],
            'streams.*.capacity' => ['nullable', 'integer', 'min:1'],
            'streams.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = collect($this->input('streams', []));
            $seen = [];

            foreach ($rows as $index => $row) {
                $key = ($row['school_class_id'] ?? '').'|'.strtolower(trim($row['name'] ?? ''));

                if (isset($seen[$key])) {
                    $validator->errors()->add("streams.{$index}.name", 'This stream name is repeated for the same class in this batch.');
                }
                $seen[$key] = true;

                if (! empty($row['school_class_id']) && ! empty($row['name'])) {
                    $exists = \App\Models\Stream::where('school_class_id', $row['school_class_id'])
                        ->whereRaw('LOWER(name) = ?', [strtolower(trim($row['name']))])
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add("streams.{$index}.name", 'A stream with this name already exists for this class.');
                    }
                }
            }
        });
    }
}
