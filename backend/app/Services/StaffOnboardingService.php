<?php

namespace App\Services;

use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a staff User account (first password = their own phone number)
 * plus its StaffProfile with a sequential employee number, in one
 * transaction.
 */
class StaffOnboardingService
{
    public function __construct(protected EmployeeNumberGenerator $employeeNumbers)
    {
    }

    /**
     * @param  array{user: array, profile: array}  $data
     * @return array{user: User, temporary_password: string}
     */
    public function onboard(School $school, array $data): array
    {
        return DB::transaction(function () use ($school, $data) {
            // The first password is simply their own phone number — easy to
            // remember/communicate, no random string to write down. They
            // change it after signing in, from the app's or portal's own
            // Change Password screen. StoreStaffRequest requires a phone
            // number for exactly this reason.
            $temporaryPassword = $data['user']['phone'];

            $user = User::create([
                'school_id' => $school->id,
                'name' => $data['user']['name'],
                'email' => $data['user']['email'],
                'phone' => $data['user']['phone'],
                'password' => $temporaryPassword,
                'status' => 'active',
            ]);

            $user->assignRole($data['user']['role']);

            StaffProfile::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'employee_number' => $this->employeeNumbers->next($school),
                ...$data['profile'],
            ]);

            return ['user' => $user->load(['roles', 'staffProfile']), 'temporary_password' => $temporaryPassword];
        });
    }
}
