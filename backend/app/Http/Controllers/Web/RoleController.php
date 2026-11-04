<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\RoleUserCount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * See App\Http\Controllers\Api\V1\RoleController for why every method
 * here explicitly checks tenant ownership, and RoleUserCount's docblock
 * for why user counts are read via a direct query rather than
 * Role::users()/withCount('users').
 */
class RoleController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $this->authorize('roles.view');
        $tenant->assertBound();

        $roles = Role::query()
            ->where('school_id', $tenant->id())
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->with('permissions')
            ->orderBy('name')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        $counts = RoleUserCount::map($roles->pluck('id'));

        return view('school-admin.roles.index', [
            'roles' => $roles,
            'userCounts' => $counts,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request, TenantContext $tenant)
    {
        $tenant->assertBound();

        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'school_id' => $tenant->id(),
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('school-admin.roles.index')->with('status', __('Role ":p1" created.', ['p1' => $role->name]));
    }

    public function update(UpdateRoleRequest $request, Role $role, TenantContext $tenant)
    {
        $this->ensureOwnedByTenant($role, $tenant);

        $data = $request->validated();

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('school-admin.roles.index')->with('status', __('Role ":p1" updated.', ['p1' => $role->name]));
    }

    public function destroy(Role $role, TenantContext $tenant)
    {
        $this->authorize('roles.manage');
        $this->ensureOwnedByTenant($role, $tenant);

        if (RoleUserCount::for($role) > 0) {
            throw ValidationException::withMessages([
                'role' => ['This role is still assigned to one or more users. Reassign them before deleting it.'],
            ])->redirectTo(route('school-admin.roles.index'));
        }

        $role->delete();

        return redirect()->route('school-admin.roles.index')->with('status', 'Role deleted.');
    }

    protected function ensureOwnedByTenant(Role $role, TenantContext $tenant): void
    {
        if ($tenant->id() === null || (string) $role->school_id !== (string) $tenant->id()) {
            abort(404);
        }
    }

    protected function permissionGroups()
    {
        return Permission::query()->orderBy('name')->get()
            ->groupBy(fn ($permission) => explode('.', $permission->name)[0]);
    }
}
