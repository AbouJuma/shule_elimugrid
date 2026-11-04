<?php

namespace App\Http\Requests\Hostel;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateRoomRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ResolvesUuidReferences;

    protected function prepareForValidation(): void
    {
        $this->resolveUuid('student_id', 'students');
    }

    public function authorize(): bool
    {
        return $this->user()->can('hostel.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
        ];
    }
}
