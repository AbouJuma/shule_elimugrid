<?php

namespace App\Http\Requests\HR;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        // The route param is a StaffProfile on the portal (school-admin.staff.update)
        // but a User on the API (Api\V1\StaffController::update) — either one gets
        // us to the User being edited, for the email-uniqueness/self-role-change checks.
        $routeStaffProfile = $this->route('staffProfile');
        $routeUser = $this->route('user');
        $editingUserId = $routeUser?->id ?? $routeStaffProfile?->user_id;

        return [
            // Staff profile fields (unchanged).
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract'])],
            'date_joined' => ['required', 'date'],
            'date_left' => ['nullable', 'date', 'after_or_equal:date_joined'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'next_of_kin_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30'],

            // The underlying account — a name typo, an email change, or
            // reassigning which role they hold (the actual "who can do
            // what" lever). All optional: a form only touching profile
            // fields shouldn't be forced to resend these.
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editingUserId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => [
                'sometimes', 'string',
                Rule::exists('roles', 'name')->where('school_id', $schoolId)->where('guard_name', 'web'),
            ],
        ];
    }
}
