<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * "My own account" — currently just the password. Deliberately open to
 * every authenticated user regardless of role or permission (same as the
 * app's /auth/password endpoint): changing your own password isn't gated
 * behind a permission, it's a floor capability of having a login at all.
 */
class AccountController extends \App\Http\Controllers\Controller
{
    public function editPassword()
    {
        return view('account.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('The current password is not correct.')]);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        // Signs out every other session/device (API tokens included) —
        // this one (the portal session) stays signed in.
        $user->tokens()->delete();

        return redirect()->route('school-admin.account.password.edit')->with('status', __('Password changed.'));
    }
}
