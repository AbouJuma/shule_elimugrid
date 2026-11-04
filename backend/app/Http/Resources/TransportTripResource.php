<?php

namespace App\Http\Resources;

use App\Models\Student;
use App\Models\TransportTrip;
use App\Models\TripLocation;
use App\Services\TripService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trip for the mobile app. `detail` adds the stops (in visiting order, with
 * arrival times and student counts), the latest position and the travelled trail.
 *
 * @mixin TransportTrip
 */
class TransportTripResource extends JsonResource
{
    public bool $detail = false;

    public function detailed(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $trip = $this->resource;
        $trip->loadMissing(['vehicle.driver', 'route']);

        $base = [
            'id' => $trip->uuid,
            'status' => $trip->status,
            'direction' => $trip->direction,
            'started_at' => $trip->started_at->toIso8601String(),
            'ended_at' => $trip->ended_at?->toIso8601String(),
            'vehicle' => [
                'id' => $trip->vehicle->uuid,
                'registration_number' => $trip->vehicle->registration_number,
                'capacity' => $trip->vehicle->capacity,
                'driver' => $trip->vehicle->driver ? ['name' => $trip->vehicle->driver->name, 'phone' => $trip->vehicle->driver->phone] : null,
            ],
            'route' => ['id' => $trip->route->uuid, 'name' => $trip->route->name],
        ];

        if (! $this->detail) {
            return $base;
        }

        $service = app(TripService::class);
        $stops = $service->orderedStops($trip);
        $visits = $trip->visits()->get()->keyBy('route_stop_id');
        $next = $trip->isActive() ? $service->nextStop($trip) : null;

        $students = Student::withoutGlobalScopes()
            ->where('school_id', $trip->school_id)->where('status', 'active')
            ->whereIn('route_stop_id', $stops->pluck('id'))
            ->selectRaw('route_stop_id, count(*) as c')->groupBy('route_stop_id')->pluck('c', 'route_stop_id');

        $last = TripLocation::where('transport_trip_id', $trip->id)->latest('id')->first();

        return $base + [
            'stops' => $stops->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'sequence' => $s->sequence,
                'latitude' => $s->latitude !== null ? (float) $s->latitude : null,
                'longitude' => $s->longitude !== null ? (float) $s->longitude : null,
                'pickup_time' => $s->pickup_time, 'dropoff_time' => $s->dropoff_time,
                'students' => (int) ($students[$s->id] ?? 0),
                'arrived_at' => $visits->get($s->id)?->arrived_at->toIso8601String(),
                'is_next' => $next?->id === $s->id,
            ])->values(),
            'progress' => ['visited' => $visits->count(), 'total' => $stops->count()],
            'next' => $trip->isActive() ? $service->etaToNext($trip) : null,
            'position' => $last ? [
                'latitude' => $last->latitude, 'longitude' => $last->longitude,
                'speed_kmh' => $last->speed_kmh, 'recorded_at' => $last->recorded_at->toIso8601String(),
            ] : null,
            'trail' => $this->trail($trip),
        ];
    }

    /**
     * The travelled path as [lat, lng] pairs, thinned to at most ~400 points
     * so a long trip doesn't make the payload (or the map) heavy.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected function trail(TransportTrip $trip): array
    {
        $points = TripLocation::where('transport_trip_id', $trip->id)->orderBy('id')->get(['latitude', 'longitude']);
        $step = max(1, (int) ceil($points->count() / 400));

        return $points->filter(fn ($p, $i) => $i % $step === 0 || $i === $points->count() - 1)
            ->map(fn ($p) => [$p->latitude, $p->longitude])->values()->all();
    }
}
