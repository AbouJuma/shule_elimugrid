<?php

namespace App\Services;

use App\Models\School;
use Spatie\Permission\Models\Role;

/**
 * Creates the standard set of roles for a newly onboarded school (spec
 * section 8). Roles are team-scoped (school_id) via Spatie's teams
 * feature, so each school owns and can freely customize its own copies.
 */
class DefaultRoleProvisioner
{
    public function provision(School $school): void
    {
        setPermissionsTeamId($school->id);

        foreach (config('roles.default_school_roles') as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($roleName === 'School Administrator') {
                $role->syncPermissions(config('roles.school_administrator_permissions'));
            } elseif ($roleName === 'Teacher') {
                $role->syncPermissions(config('roles.teacher_permissions'));
            } elseif ($roleName === 'Accountant') {
                $role->syncPermissions(config('roles.accountant_permissions'));
            } elseif ($roleName === 'Transport Manager') {
                $role->syncPermissions(config('roles.transport_manager_permissions'));
            } elseif ($roleName === 'Driver') {
                $role->syncPermissions(config('roles.driver_permissions'));
            } elseif ($roleName === 'Parent') {
                $role->syncPermissions(config('roles.parent_permissions'));
            } elseif ($roleName === 'HR') {
                $role->syncPermissions(config('roles.hr_permissions'));
            } elseif ($roleName === 'Storekeeper') {
                $role->syncPermissions(config('roles.storekeeper_permissions'));
            } elseif ($roleName === 'Librarian') {
                $role->syncPermissions(config('roles.librarian_permissions'));
            } elseif ($roleName === 'Warden') {
                $role->syncPermissions(config('roles.warden_permissions'));
            } elseif ($roleName === 'Receptionist') {
                $role->syncPermissions(config('roles.receptionist_permissions'));
            }
        }
    }
}
