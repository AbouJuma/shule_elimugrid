<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Counts users assigned to a role via a direct pivot-table query rather
 * than Spatie's Role::users() relation.
 *
 * Role::users() resolves its target model via
 * getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')).
 * withCount()/loadCount() build that relation against a template (unsaved)
 * Role instance with no guard_name attribute yet, so it always falls back
 * to config('auth.defaults.guard') — and Laravel's `auth:sanctum`
 * middleware calls Auth::shouldUse('sanctum'), which overwrites that same
 * config key for the rest of the request. 'sanctum' isn't a normal
 * provider-based guard, so getModelForGuard('sanctum') returns null and
 * the relation crashes with "Class name must be a valid object or a
 * string". This bypasses the relation entirely to sidestep it.
 */
class RoleUserCount
{
    public static function for(Role $role): int
    {
        return static::map([$role->id])[$role->id] ?? 0;
    }

    /**
     * @param  iterable<int>  $roleIds
     * @return array<int, int> role id => user count
     */
    public static function map(iterable $roleIds): array
    {
        $roleIds = collect($roleIds)->values();

        if ($roleIds->isEmpty()) {
            return [];
        }

        $pivotRole = app(PermissionRegistrar::class)->pivotRole;

        return DB::table(config('permission.table_names.model_has_roles'))
            ->whereIn($pivotRole, $roleIds)
            ->groupBy($pivotRole)
            ->selectRaw("{$pivotRole} as role_id, count(*) as aggregate")
            ->pluck('aggregate', 'role_id')
            ->all();
    }
}
