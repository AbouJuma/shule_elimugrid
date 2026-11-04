<?php

namespace App\Http\Requests\HR;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Onboards a staff member: creates the User account (with a role, same
 * as the existing users.create flow) plus a StaffProfile in one
 * submission — mirrors StoreStudentRequest's student+guardians shape.
 */
class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            // Required, not just optional contact info: it becomes their
            // first login password (see StaffOnboardingService), so there
            // has to be one to assign.
            'user.phone' => ['required', 'string', 'max:30'],
            'user.role' => [
                'required', 'string',
                Rule::exists('roles', 'name')->where('school_id', $schoolId)->where('guard_name', 'web'),
            ],

            'profile' => ['required', 'array'],
            'profile.job_title' => ['nullable', 'string', 'max:150'],
            'profile.department' => ['nullable', 'string', 'max:150'],
            'profile.employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract'])],
            'profile.date_joined' => ['required', 'date'],
            'profile.national_id' => ['nullable', 'string', 'max:50'],
            'profile.address' => ['nullable', 'string', 'max:1000'],
            'profile.next_of_kin_name' => ['nullable', 'string', 'max:255'],
            'profile.next_of_kin_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
