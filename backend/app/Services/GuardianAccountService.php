<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login accounts for parents: a guardian gets a User with the Parent role,
 * linked through guardians.user_id. What a parent can then see is decided by
 * that link (their own children only), not by a broad permission. Shared by
 * the API (app) and portal (Web) controllers so both stay in sync.
 */
class GuardianAccountService
{
    public function __construct(protected SmsService $sms)
    {
    }

    /**
     * $email / $phone: which identifier to sign in with — explicitly chosen
     * by whoever is creating the login (falls back to the guardian's own
     * email on file when neither is given, for the existing email-only
     * callers). Exactly one ends up as the account's sign-in key; the other
     * user column may still get filled in for display/SMS purposes.
     *
     * @return array{guardian: Guardian, password: string}
     */
    public function createLogin(School $school, Guardian $guardian, ?string $email, ?string $phone, User $by): array
    {
        if ($guardian->user_id !== null) {
            throw ValidationException::withMessages(['email' => [__('This guardian already has a login.')]]);
        }

        $email = $email ?: null;
        $phone = $phone ?: null;

        if (! $email && ! $phone) {
            $email = $guardian->email;
        }

        if (! $email && ! $phone) {
            throw ValidationException::withMessages(['email' => [__('An email address or phone number is required to create a login.')]]);
        }

        if ($email && User::withoutGlobalScopes()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => [__('That email address is already used by another account.')]]);
        }

        if ($phone && User::withoutGlobalScopes()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => [__('That phone number is already used by another account.')]]);
        }

        // First password is the guardian's own phone number — easy to
        // remember/communicate at the gate or over SMS, even when it's also
        // the login itself (deliberate: simplest possible first login,
        // "ataibadilisha akifika ndani"). Falls back to a random one only if
        // no phone number exists at all. They're expected to change it
        // straight after signing in, from the app's/portal's own Change
        // Password screen.
        $loginPhone = $phone ?: $guardian->phone;
        $password = $loginPhone ?: Str::password(12, symbols: false);

        $user = User::create([
            'school_id' => $school->id,
            'name' => $guardian->name,
            'email' => $email,
            'phone' => $phone ?: $guardian->phone,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        setPermissionsTeamId($school->id);
        $user->assignRole('Parent');

        $guardian->update(['user_id' => $user->id, 'email' => $guardian->email ?: $email]);

        $this->text($school, $guardian, $email ?: $phone, $password, $by);

        return ['guardian' => $guardian->fresh('user'), 'password' => $password];
    }

    /** @return array{guardian: Guardian, password: string} */
    public function resetLogin(School $school, Guardian $guardian, User $by): array
    {
        if (! $guardian->user) {
            throw ValidationException::withMessages(['guardian' => [__('This guardian has no login to reset.')]]);
        }

        $password = Str::password(12, symbols: false);
        $guardian->user->update(['password' => Hash::make($password)]);
        $guardian->user->tokens()->delete(); // sign them out everywhere

        $this->text($school, $guardian, $guardian->user->email ?: $guardian->user->phone, $password, $by);

        return ['guardian' => $guardian->fresh('user'), 'password' => $password];
    }

    protected function text(School $school, Guardian $guardian, string $login, string $password, User $by): void
    {
        if (! $guardian->phone) {
            return;
        }

        // Worded by the school's own "Login details" template, in the parent's language.
        $templates = app(MessageTemplateService::class);
        $message = $templates->render($school, 'account_login', $templates->localeFor($school, $guardian), [
            'guardian_name' => $guardian->name, 'school_name' => $school->name, 'login' => $login, 'password' => $password,
        ]);

        $this->sms->sendToPhone($school->id, $guardian->phone, $guardian->name, $message, 'account', $by);
    }
}
