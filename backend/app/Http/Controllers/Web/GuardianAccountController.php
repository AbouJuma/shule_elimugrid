<?php

namespace App\Http\Controllers\Web;

use App\Models\Guardian;
use App\Services\GuardianAccountService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Login accounts for parents, from the portal — the same
 * App\Services\GuardianAccountService the app's API controller uses. The
 * temporary password is shown once via a session-flashed value the student
 * view modal picks up after the redirect (it can't survive a page reload).
 */
class GuardianAccountController extends \App\Http\Controllers\Controller
{
    public function store(Request $request, Guardian $guardian, TenantContext $tenant, GuardianAccountService $accounts)
    {
        $this->authorize('guardians.manage');

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $accounts->createLogin($tenant->assertBound(), $guardian, $data['email'] ?? null, $data['phone'] ?? null, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with([
            'guardian_login_name' => $result['guardian']->name,
            'guardian_login_email' => $result['guardian']->user->email ?: $result['guardian']->user->phone,
            'guardian_login_password' => $result['password'],
        ]);
    }

    public function reset(Request $request, Guardian $guardian, TenantContext $tenant, GuardianAccountService $accounts)
    {
        $this->authorize('guardians.manage');

        try {
            $result = $accounts->resetLogin($tenant->assertBound(), $guardian, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with([
            'guardian_login_name' => $result['guardian']->name,
            'guardian_login_email' => $result['guardian']->user->email ?: $result['guardian']->user->phone,
            'guardian_login_password' => $result['password'],
        ]);
    }
}
