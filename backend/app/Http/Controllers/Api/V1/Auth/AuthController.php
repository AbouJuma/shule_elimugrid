<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountStatusGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AccountStatusGuard $accountStatusGuard)
    {
        $data = $request->validated();

        // The "email" field doubles as a phone number for parent accounts
        // created with no email on file — resolve by whichever it looks like.
        /** @var User|null $user */
        $user = str_contains($data['email'], '@')
            ? User::allTenants()->where('email', $data['email'])->first()
            : User::allTenants()->where('phone', $data['email'])->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $data['password']])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $accountStatusGuard->ensureLoginAllowed($user);

        $token = $user->createToken($data['device_name'])->plainTextToken;

        // This request never passes through IdentifyTenant (no authenticated
        // user exists until now), so Spatie's team context is still unset.
        // Set it explicitly before loading roles, or a school-scoped user's
        // roles/permissions would resolve against team_id = null and come
        // back empty.
        setPermissionsTeamId($user->school_id);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->load('roles')),
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /** Change your own password. Other devices are signed out; this one stays. */
    public function password(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => [__('The current password is not correct.')]]);
        }

        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($data['password'])]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['data' => ['changed' => true]]);
    }
}
