<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * School-scoped user management. Deliberately does NOT accept a school_id
 * from the client anywhere — every query and write here is implicitly
 * confined to the requesting user's tenant via User's global "tenant"
 * scope (see App\Models\User::booted()), which reads from TenantContext,
 * which is set only by IdentifyTenant from the authenticated user.
 *
 * That global scope is a no-op when no tenant is bound (platform/Super
 * Admin context) by design — School::query() etc. need that for platform
 * tooling. But THIS endpoint is meant for school admins only, so a
 * Super Admin token (which legitimately has users.view) must be blocked
 * explicitly, or it would return every user across every school.
 */
class UserController extends Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $this->authorize('users.view');
        $tenant->assertBound();

        $users = User::with('roles')
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request, TenantContext $tenant)
    {
        $tenant->assertBound();

        $data = $request->validated();
        $temporaryPassword = Str::password(14);

        $user = User::create([
            'school_id' => $tenant->id(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $temporaryPassword,
            'status' => 'active',
        ]);

        $user->assignRole($data['role']);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->load('roles')),
                'temporary_password' => $temporaryPassword,
            ],
        ], 201);
    }
}
