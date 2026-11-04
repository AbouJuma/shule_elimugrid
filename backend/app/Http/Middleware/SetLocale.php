<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the interface language for the request: an explicit choice stored
 * in the session wins, then the signed-in user's saved preference, then
 * the app default. Only locales listed in config('app.supported_locales')
 * are ever applied, so a tampered session value can't select a missing
 * language file.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('app.supported_locales'));

        // The API is stateless (no session) — there the saved user preference decides.
        $locale = ($request->hasSession() ? $request->session()->get('locale') : null) ?? $request->user()?->locale;

        if ($locale && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
