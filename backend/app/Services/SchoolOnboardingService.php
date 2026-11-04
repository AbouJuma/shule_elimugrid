<?php

namespace App\Services;

use App\Mail\SchoolOnboardedMail;
use App\Models\Campus;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Implements the Super Admin "create school" workflow from spec section 6:
 * school info -> package -> first School Administrator -> tenant identity
 * -> default configuration -> credentials emailed to the new admin (and
 * still handed back to Super Admin too, in case delivery fails or the
 * school's real inbox isn't checked yet).
 */
class SchoolOnboardingService
{
    public function __construct(protected DefaultRoleProvisioner $roleProvisioner)
    {
    }

    /**
     * @param  array{school: array, admin: array, subscription_package_id: ?int}  $data
     * @return array{school: School, admin: User, temporary_password: string}
     */
    public function onboard(array $data): array
    {
        $result = DB::transaction(function () use ($data) {
            $school = School::create([
                'timezone' => 'Africa/Dar_es_Salaam',
                'currency' => 'TZS',
                ...$data['school'],
                'slug' => $this->uniqueSlug($data['school']['name']),
                'status' => 'active',
                'subscription_package_id' => $data['subscription_package_id'] ?? null,
            ]);

            if ($data['subscription_package_id'] ?? null) {
                SchoolSubscription::create([
                    'school_id' => $school->id,
                    'subscription_package_id' => $data['subscription_package_id'],
                    'starts_at' => now(),
                    'status' => 'active',
                ]);
            }

            Campus::create([
                'school_id' => $school->id,
                'name' => $data['school']['name'].' - Main Campus',
                'code' => 'MAIN',
                'is_main' => true,
            ]);

            $this->roleProvisioner->provision($school);

            $temporaryPassword = Str::password(14);

            $admin = User::create([
                'school_id' => $school->id,
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'phone' => $data['admin']['phone'] ?? null,
                'password' => $temporaryPassword,
                'status' => 'active',
            ]);

            setPermissionsTeamId($school->id);
            $admin->assignRole('School Administrator');

            return [
                'school' => $school,
                'admin' => $admin,
                'temporary_password' => $temporaryPassword,
            ];
        });

        // Sent only after the transaction commits — never email real credentials
        // for a school that a later failure in the same transaction rolled back.
        // Best-effort: a bad mail configuration must never undo a successful
        // onboarding; Super Admin still gets the password back either way.
        try {
            Mail::to($result['admin']->email)->send(new SchoolOnboardedMail($result['school'], $result['admin'], $result['temporary_password']));
        } catch (\Throwable $e) {
            Log::warning('School onboarding email failed to send', ['school' => $result['school']->name, 'admin_email' => $result['admin']->email, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (School::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
