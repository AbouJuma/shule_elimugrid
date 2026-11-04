<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Support\Tenancy\RoleUserCount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * School-scoped role management (spec section 8: "Schools must be able to
 * create custom roles"). Roles are Spatie "team" rows keyed by school_id,
 * but Role has no BelongsToTenant-style global scope of its own, so route
 * model binding would happily resolve ANY school's role by id. Every
 * method here explicitly verifies role->school_id against the current
 * tenant before doing anything with it — skipping that check would let
 * one school edit or delete another school's roles by guessing an id.
 *
 * Also guards against a platform (Super Admin) user reaching this
 * controller at all: their TenantContext id is null, and the PLATFORM
 * Super Admin role itself is stored with school_id = null — without the
 * assertBound() check, a platform user landing here would be
 * managing their own platform role through a UI meant for schools.
 *
 * User counts are read via RoleUserCount (a direct pivot-table query)
 * rather than Role::users()/withCount('users') — see that class's
 * docblock for why the relation-based count crashes under Sanctum auth.
 */
class RoleController extends Controller
{
    public function index(TenantContext $tenant)
    {
        $this->authorize('roles.view');
        $tenant->assertBound();

        $roles = Role::query()
            ->where('school_id', $tenant->id())
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $counts = RoleUserCount::map($roles->pluck('id'));

        return RoleResource::collection(
            $roles->map(fn ($role) => (new RoleResource($role))->withUsersCount($counts[$role->id] ?? 0))
        );
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

        return response()->json([
            'data' => (new RoleResource($role->load('permissions')))->withUsersCount(0),
        ], 201);
    }

    public function show(Role $role, TenantContext $tenant)
    {
        $this->authorize('roles.view');
        $this->ensureOwnedByTenant($role, $tenant);

        return (new RoleResource($role->load('permissions')))->withUsersCount(RoleUserCount::for($role));
    }

    public function update(UpdateRoleRequest $request, Role $role, TenantContext $tenant)
    {
        $this->ensureOwnedByTenant($role, $tenant);

        $data = $request->validated();

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        $role = $role->fresh()->load('permissions');

        return (new RoleResource($role))->withUsersCount(RoleUserCount::for($role));
    }

    public function destroy(Role $role, TenantContext $tenant)
    {
        $this->authorize('roles.manage');
        $this->ensureOwnedByTenant($role, $tenant);

        if (RoleUserCount::for($role) > 0) {
            throw ValidationException::withMessages([
                'role' => ['This role is still assigned to one or more users. Reassign them before deleting it.'],
            ]);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * 404s rather than 403s so a school can't distinguish "this role
     * belongs to someone else" from "this role doesn't exist" — either
     * way, nothing about another tenant's roles should be inferable.
     * A null tenant id NEVER matches, even if the role itself also has a
     * null school_id (the platform's own roles) — see class docblock.
     */
    protected function ensureOwnedByTenant(Role $role, TenantContext $tenant): void
    {
        if ($tenant->id() === null || (string) $role->school_id !== (string) $tenant->id()) {
            abort(404);
        }
    }
}
