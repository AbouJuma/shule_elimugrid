<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for "is this account allowed to sign in right
 * now" (spec section 7: suspended schools, section 3: suspended users).
 * Shared between the API (Sanctum) and web (session) login controllers so
 * this security rule can't drift between the two entry points.
 */
class AccountStatusGuard
{
    public function ensureLoginAllowed(User $user): void
    {
        if ($user->status === 'suspended') {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended.'],
            ]);
        }

        if ($user->school_id && (! $user->school || $user->school->status === 'suspended')) {
            throw ValidationException::withMessages([
                'email' => ['This school account is suspended. Contact the platform administrator.'],
            ]);
        }
    }
}
