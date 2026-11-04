<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;

class LocaleController extends \App\Http\Controllers\Controller
{
    public function update(Request $request, string $locale)
    {
        abort_unless(array_key_exists($locale, config('app.supported_locales')), 404);

        $request->session()->put('locale', $locale);

        // Remember the choice across sessions/devices for signed-in users.
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
