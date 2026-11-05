<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\RoleUserCount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One page for "who can do what": roles (create/edit/delete, and which
 * permissions each one carries) and which role each staff member holds —
 * previously two separate, hard-to-find pages. Role CRUD still posts to
 * the existing RoleController routes (school-admin.roles.*); this
 * controller only gathers the combined data for one view. The full,
 * searchable/paginated Staff page (with job title, salary, etc.) is
 * still the place for everything else about a staff member — this page
 * only exposes the role, as a quick way to change it without leaving
 * Settings.
 */
class SettingsController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $this->authorize('roles.view');
        $school = $tenant->assertBound();

        $roles = Role::query()
            ->where('school_id', $school->id)
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->with('permissions')
            ->orderBy('name')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        $allRoles = Role::query()->where('school_id', $school->id)->orderBy('name')->get();

        return view('school-admin.settings.index', [
            'roles' => $roles,
            'userCounts' => RoleUserCount::map($roles->pluck('id')),
            'permissionGroups' => Permission::query()->orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]),
            'allRoles' => $allRoles,
            'staff' => $request->user()->can('hr.view')
                ? User::query()->whereHas('staffProfile')->with(['roles', 'staffProfile'])->orderBy('name')->get()
                : collect(),
        ]);
    }
}
