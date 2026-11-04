<?php

namespace App\Http\Requests\HR;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkStaffAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('staff_attendance.mark');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.user_id' => [
                'required',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
