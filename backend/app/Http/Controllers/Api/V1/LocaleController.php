<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /** Saves the app's language on the user so the PDFs it downloads match. */
    public function update(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(config('app.supported_locales')))]]);

        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return response()->json(['data' => ['locale' => $data['locale']]]);
    }
}
