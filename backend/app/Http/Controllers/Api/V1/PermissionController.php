<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

/**
 * Read-only listing of the global permission catalog (not tenant-scoped —
 * see PermissionSeeder). Used to build the permission picker when
 * creating/editing a role.
 */
class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('roles.view');

        $permissions = Permission::query()->orderBy('name')->get(['id', 'name']);

        $grouped = $permissions->groupBy(fn ($permission) => explode('.', $permission->name)[0]);

        return response()->json([
            'data' => $grouped->map(fn ($group) => $group->pluck('name')->values()),
        ]);
    }
}
