<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Propagates a newly-added default permission to every EXISTING tenant's
 * matching role — the missing half of "add a permission to config/roles.php"
 * for a multi-tenant app: DefaultRoleProvisioner only runs when a school is
 * first onboarded, so a permission added to, say,
 * school_administrator_permissions after schools already exist would
 * otherwise only ever reach schools created from that point on.
 *
 * Deliberately ADDITIVE ONLY (givePermissionTo, never syncPermissions) —
 * a school can customise its own copy of any default role from the Roles
 * page (see RoleController), and re-syncing the FULL config list against
 * every tenant would silently wipe that out. This only ever grants
 * permissions explicitly listed in config('roles.additive_grants'); it
 * never removes one, and it never touches a role a school added itself.
 *
 * Usage: after adding a permission to a role's default set in code, also
 * add it to config('roles.additive_grants'), then run:
 *   php artisan tenants:sync-permissions
 * This is safe to run repeatedly (idempotent) and is the supported way to
 * roll a new default permission out to every school that already exists.
 */
class SyncTenantPermissions extends Command
{
    protected $signature = 'tenants:sync-permissions {--school= : Only this school\'s ID, for a dry-run-ish spot check}';

    protected $description = "Grant every school's roles whatever config('roles.additive_grants') says they should have by now (additive only, never removes anything)";

    public function handle(): int
    {
        $grants = config('roles.additive_grants', []);

        if (empty($grants)) {
            $this->info('Nothing in config(\'roles.additive_grants\') — nothing to do.');

            return self::SUCCESS;
        }

        $schools = School::query()->when($this->option('school'), fn ($q, $id) => $q->whereKey($id))->get();
        $granted = 0;

        foreach ($schools as $school) {
            setPermissionsTeamId($school->id);

            foreach ($grants as $permission => $roleNames) {
                foreach ((array) $roleNames as $roleName) {
                    $role = Role::where('name', $roleName)->where('school_id', $school->id)->first();

                    if (! $role) {
                        continue; // this school doesn't have that role (renamed/deleted it) — leave it alone
                    }

                    if (! $role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                        $granted++;
                        $this->line("  {$school->name} / {$roleName}: +{$permission}");
                    }
                }
            }
        }

        $this->info("Checked {$schools->count()} school(s); granted {$granted} new permission assignment(s).");

        return self::SUCCESS;
    }
}
