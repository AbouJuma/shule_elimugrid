<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the platform-level (school_id = null) Super Admin role and the
 * first Super Admin user. Roles/permissions here are NOT team-scoped
 * (team_foreign_key null) since Super Admin operates outside any tenant.
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        // Platform role/user must never inherit a team id left over from a
        // prior school-scoped seeder run in the same process.
        setPermissionsTeamId(null);

        $superAdminRole = Role::findOrCreate(config('roles.super_admin_role'), 'web');
        $superAdminRole->syncPermissions(Permission::all());

        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@elimugrid.test');

        if (User::allTenants()->where('email', $email)->exists()) {
            return;
        }

        $password = env('SUPER_ADMIN_PASSWORD');
        $generated = false;

        if (! $password) {
            $password = Str::password(16);
            $generated = true;
        }

        $admin = User::create([
            'school_id' => null,
            'name' => env('SUPER_ADMIN_NAME', 'Platform Super Admin'),
            'email' => $email,
            'password' => $password,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $admin->assignRole($superAdminRole);

        if ($generated) {
            $this->command?->warn("Super Admin created: {$email} / {$password} — store this securely, it will not be shown again.");
        }
    }
}
