<?php

namespace App\Http\Controllers;

use App\Services\PlaceSearchService;
use Illuminate\Http\Request;

/**
 * Shared between the portal (session auth) and API/mobile clients (token
 * auth) — same dual-guard pattern as CertificatePdfController. No
 * dedicated permission gate: this is a geocoding helper, not sensitive
 * data, so any authenticated tenant user can search it.
 */
class PlaceController extends Controller
{
    public function search(Request $request, PlaceSearchService $places)
    {
        $results = $places->search($request->string('q')->toString());

        return response()->json([
            'data' => $results->map(fn ($place) => [
                'id' => $place->id,
                'name' => $place->name,
                'latitude' => (float) $place->latitude,
                'longitude' => (float) $place->longitude,
            ]),
        ]);
    }
}
