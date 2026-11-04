<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransportTripResource;
use App\Models\TransportTrip;
use Illuminate\Http\Request;

/** The portal's live map of every bus that is currently on a trip. */
class TransportLiveController extends Controller
{
    public function index()
    {
        $this->authorize('transport.manage');

        return view('school-admin.transport.live');
    }

    /** Polled by the map every few seconds. */
    public function data(Request $request)
    {
        $this->authorize('transport.manage');

        $trips = TransportTrip::query()->with(['vehicle.driver', 'route'])->where('status', 'in_progress')->latest('started_at')->get();

        return response()->json(['data' => $trips->map(fn ($t) => (new TransportTripResource($t))->detailed()->resolve($request))->values()]);
    }
}
