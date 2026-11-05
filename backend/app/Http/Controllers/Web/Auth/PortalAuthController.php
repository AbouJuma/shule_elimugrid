<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountStatusGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PortalAuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, AccountStatusGuard $accountStatusGuard)
    {
        $credentials = $request->validate([
            // Not validated as an email format — a parent account may have
            // only a phone number, which is sent through this same field.
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = str_contains($credentials['email'], '@')
            ? User::allTenants()->where('email', $credentials['email'])->first()
            : User::allTenants()->where('phone', $credentials['email'])->first();

        if (! $user || ! Auth::attempt(['id' => $user->id, 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        try {
            $accountStatusGuard->ensureLoginAllowed(Auth::user());
            $this->ensureNotAppOnlyAccount(Auth::user());
        } catch (ValidationException $e) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw $e;
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * A guardian/parent (or, once that module exists, a student) account
     * is for the mobile app only — it's deliberately given zero
     * permissions (see config('roles.parent_permissions')), but several
     * staff-portal sidebar items have no permission gate at all because
     * they were written assuming only staff could ever reach this portal.
     * Reject here instead of relying on every such item to check a
     * permission a parent will never have.
     */
    protected function ensureNotAppOnlyAccount(User $user): void
    {
        if (! $user->school_id) {
            return;
        }

        setPermissionsTeamId($user->school_id);
        $roleNames = $user->roles->pluck('name');

        if ($roleNames->isNotEmpty() && $roleNames->diff(config('roles.app_only_roles'))->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => [__('This account is for the ElimuGrid mobile app. Please sign in there instead.')],
            ]);
        }
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
