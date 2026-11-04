<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransportTripResource;
use App\Models\RouteStop;
use App\Models\TransportRoute;
use App\Models\TransportTrip;
use App\Models\Vehicle;
use App\Services\TripService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bus trips for the mobile app.
 *   - The assigned driver starts a trip, streams positions, marks stops and ends it.
 *   - Staff with transport.manage watch every trip in the school.
 *   - Roles that only have transport.view (e.g. the Parent role, until guardian
 *     accounts are linked to students) get nothing here: a bus's live position
 *     is not something to show to everyone.
 */
class TripController extends Controller
{
    /** Trips the user may look at: everything for transport managers, only their own for a driver. */
    protected function visible(Request $request)
    {
        $user = $request->user();

        if ($user->can('transport.manage')) {
            return TransportTrip::query();
        }

        abort_unless($user->can('transport.track'), 403);

        return TransportTrip::query()->where('driver_user_id', $user->id);
    }

    public function index(Request $request)
    {
        $trips = $this->visible($request)
            ->with(['vehicle.driver', 'route'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('started_at')
            ->paginate($request->integer('per_page', 20));

        return TransportTripResource::collection($trips);
    }

    /** Trips running right now. */
    public function active(Request $request)
    {
        $trips = $this->visible($request)->with(['vehicle.driver', 'route'])->where('status', 'in_progress')->latest('started_at')->get();

        return TransportTripResource::collection($trips);
    }

    /** The signed-in driver's own running trip (or null), in full detail. */
    public function current(Request $request)
    {
        abort_unless($request->user()->can('transport.track'), 403);

        $trip = TransportTrip::query()->where('driver_user_id', $request->user()->id)->where('status', 'in_progress')->latest('started_at')->first();

        return response()->json(['data' => $trip ? (new TransportTripResource($trip))->detailed()->resolve($request) : null]);
    }

    public function store(Request $request, TenantContext $tenant, TripService $trips)
    {
        abort_unless($request->user()->can('transport.track'), 403);
        $school = $tenant->assertBound();

        $data = $request->validate([
            'vehicle_id' => ['required', 'string', Rule::exists('vehicles', 'uuid')->where('school_id', $school->id)],
            'route_id' => ['required', 'string', Rule::exists('transport_routes', 'uuid')->where('school_id', $school->id)],
            'direction' => ['required', Rule::in(TransportTrip::DIRECTIONS)],
        ]);

        $trip = $trips->start(
            $school, $request->user(),
            Vehicle::query()->where('uuid', $data['vehicle_id'])->firstOrFail(),
            TransportRoute::query()->where('uuid', $data['route_id'])->firstOrFail(),
            $data['direction'],
        );

        return response()->json(['data' => (new TransportTripResource($trip))->detailed()->resolve($request)], 201);
    }

    public function show(Request $request, TransportTrip $trip)
    {
        abort_unless($this->visible($request)->whereKey($trip->id)->exists(), 403);

        return response()->json(['data' => (new TransportTripResource($trip))->detailed()->resolve($request)]);
    }

    public function location(Request $request, TransportTrip $trip, TripService $trips)
    {
        $this->authorizeDriver($request, $trip);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed_kmh' => ['nullable', 'numeric', 'between:0,300'],
        ]);

        $result = $trips->recordLocation($trip, (float) $data['latitude'], (float) $data['longitude'], isset($data['speed_kmh']) ? (float) $data['speed_kmh'] : null);

        return response()->json(['data' => ['arrived_stop' => $result['arrived']?->only(['id', 'name'])]]);
    }

    public function arrive(Request $request, TransportTrip $trip, int $stop, TripService $trips)
    {
        $this->authorizeDriver($request, $trip);

        $routeStop = RouteStop::query()->where('transport_route_id', $trip->transport_route_id)->findOrFail($stop);
        $trips->arrive($trip, $routeStop);

        return response()->json(['data' => (new TransportTripResource($trip->fresh()))->detailed()->resolve($request)]);
    }

    public function end(Request $request, TransportTrip $trip, TripService $trips)
    {
        $this->authorizeDriver($request, $trip);

        $trips->end($trip);

        return response()->json(['data' => (new TransportTripResource($trip->fresh()))->detailed()->resolve($request)]);
    }

    /** Only the driver who started the trip may drive it. */
    protected function authorizeDriver(Request $request, TransportTrip $trip): void
    {
        abort_unless($request->user()->can('transport.track') && $trip->driver_user_id === $request->user()->id, 403);
    }
}
