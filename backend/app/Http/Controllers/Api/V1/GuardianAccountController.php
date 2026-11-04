<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\GuardianAccountService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Login accounts for parents — see App\Services\GuardianAccountService for
 * the actual logic, shared with the portal's equivalent controller.
 */
class GuardianAccountController extends Controller
{
    public function store(Request $request, Guardian $guardian, TenantContext $tenant, GuardianAccountService $accounts)
    {
        $this->authorize('guardians.manage');
        $school = $tenant->assertBound();

        abort_if($guardian->user_id !== null, 422, __('This guardian already has a login.'));

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')],
        ]);

        // GuardianAccountService itself throws a ValidationException keyed
        // 'email' when neither an identifier was given here nor one is on
        // file for the guardian — no need to duplicate that check here.
        $result = $accounts->createLogin($school, $guardian, $data['email'] ?? null, $data['phone'] ?? null, $request->user());

        return response()->json(['data' => $this->resource($result['guardian']), 'temporary_password' => $result['password']], 201);
    }

    /** A new temporary password, for a parent who has forgotten theirs. */
    public function reset(Request $request, Guardian $guardian, TenantContext $tenant, GuardianAccountService $accounts)
    {
        $this->authorize('guardians.manage');
        $school = $tenant->assertBound();

        abort_unless($guardian->user, 404);

        $result = $accounts->resetLogin($school, $guardian, $request->user());

        return response()->json(['data' => $this->resource($result['guardian']), 'temporary_password' => $result['password']]);
    }

    protected function resource(Guardian $g): array
    {
        return ['id' => $g->id, 'name' => $g->name, 'has_login' => $g->user_id !== null, 'login_email' => $g->user?->email, 'login_phone' => $g->user?->phone];
    }
}
