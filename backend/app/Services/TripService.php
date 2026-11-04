<?php

namespace App\Services;

use App\Models\RouteStop;
use App\Models\School;
use App\Models\TransportRoute;
use App\Models\TransportTrip;
use App\Models\TripLocation;
use App\Models\TripStopVisit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A bus trip: the driver starts it on a route, the app streams positions,
 * the bus is marked "arrived" at each stop (automatically when it comes
 * within ARRIVE_RADIUS_M of the next stop, or by the driver's tap), and the
 * driver ends it. Every query filters by school_id explicitly.
 */
class TripService
{
    /** How close (metres) the bus must get to the next stop to count as arrived. */
    public const ARRIVE_RADIUS_M = 60;

    public function start(School $school, User $driver, Vehicle $vehicle, TransportRoute $route, string $direction): TransportTrip
    {
        if ($vehicle->school_id !== $school->id || $route->school_id !== $school->id) {
            throw ValidationException::withMessages(['vehicle_id' => [__('Vehicle or route not found.')]]);
        }

        if ($vehicle->driver_user_id !== $driver->id) {
            throw ValidationException::withMessages(['vehicle_id' => [__('You are not the assigned driver of this vehicle.')]]);
        }

        if ($vehicle->status !== 'active') {
            throw ValidationException::withMessages(['vehicle_id' => [__('This vehicle is not active.')]]);
        }

        if ($route->status !== 'active') {
            throw ValidationException::withMessages(['route_id' => [__('This route is not active.')]]);
        }

        if ($route->vehicle_id !== null && $route->vehicle_id !== $vehicle->id) {
            throw ValidationException::withMessages(['route_id' => [__('This route belongs to a different vehicle.')]]);
        }

        if (! $route->stops()->exists()) {
            throw ValidationException::withMessages(['route_id' => [__('This route has no stops yet.')]]);
        }

        return DB::transaction(function () use ($school, $driver, $vehicle, $route, $direction) {
            // Lock the vehicle so two taps can't start two trips on the same bus.
            Vehicle::withoutGlobalScopes()->lockForUpdate()->findOrFail($vehicle->id);

            $running = TransportTrip::withoutGlobalScopes()
                ->where('vehicle_id', $vehicle->id)->where('status', 'in_progress')->exists();

            if ($running) {
                throw ValidationException::withMessages(['vehicle_id' => [__('This vehicle already has a trip in progress.')]]);
            }

            return TransportTrip::withoutGlobalScopes()->create([
                'school_id' => $school->id, 'vehicle_id' => $vehicle->id, 'transport_route_id' => $route->id,
                'driver_user_id' => $driver->id, 'direction' => $direction, 'status' => 'in_progress', 'started_at' => now(),
            ]);
        });
    }

    /**
     * Stores the position, refreshes the vehicle's last-known position, and
     * marks the next stop as reached if the bus is close enough to it.
     *
     * @return array{arrived: ?RouteStop}
     */
    public function recordLocation(TransportTrip $trip, float $latitude, float $longitude, ?float $speedKmh = null): array
    {
        $this->assertActive($trip);

        return DB::transaction(function () use ($trip, $latitude, $longitude, $speedKmh) {
            TripLocation::create([
                'transport_trip_id' => $trip->id, 'latitude' => $latitude, 'longitude' => $longitude,
                'speed_kmh' => $speedKmh, 'recorded_at' => now(),
            ]);

            Vehicle::withoutGlobalScopes()->whereKey($trip->vehicle_id)->first()?->recordLocation($latitude, $longitude);

            $arrived = null;
            $next = $this->nextStop($trip);

            if ($next && $next->latitude !== null && $next->longitude !== null
                && self::distanceMetres($latitude, $longitude, (float) $next->latitude, (float) $next->longitude) <= self::ARRIVE_RADIUS_M) {
                TripStopVisit::firstOrCreate(
                    ['transport_trip_id' => $trip->id, 'route_stop_id' => $next->id],
                    ['arrived_at' => now(), 'source' => 'auto'],
                );
                $arrived = $next;
            }

            return ['arrived' => $arrived];
        });
    }

    public function arrive(TransportTrip $trip, RouteStop $stop): TripStopVisit
    {
        $this->assertActive($trip);

        if ($stop->transport_route_id !== $trip->transport_route_id) {
            throw ValidationException::withMessages(['stop' => [__('That stop is not on this trip\'s route.')]]);
        }

        return TripStopVisit::firstOrCreate(
            ['transport_trip_id' => $trip->id, 'route_stop_id' => $stop->id],
            ['arrived_at' => now(), 'source' => 'manual'],
        );
    }

    public function end(TransportTrip $trip): TransportTrip
    {
        $this->assertActive($trip);

        $trip->update(['status' => 'completed', 'ended_at' => now()]);

        return $trip;
    }

    /** Stops in the order this trip visits them: sequence order in the morning, reversed in the afternoon. */
    public function orderedStops(TransportTrip $trip): Collection
    {
        $stops = RouteStop::query()->where('transport_route_id', $trip->transport_route_id)->orderBy('sequence')->get();

        return $trip->direction === 'afternoon' ? $stops->reverse()->values() : $stops;
    }

    public function nextStop(TransportTrip $trip): ?RouteStop
    {
        $visited = TripStopVisit::where('transport_trip_id', $trip->id)->pluck('route_stop_id')->all();

        return $this->orderedStops($trip)->first(fn ($s) => ! in_array($s->id, $visited, true));
    }

    /**
     * Rough time to the next stop: straight-line distance × 1.3 (roads are
     * never straight) at the bus's recent average speed, or 25 km/h when it
     * has been standing or has no speed readings. An estimate, not a promise.
     *
     * @return array{stop_id: int, name: string, distance_m: int, eta_minutes: int}|null
     */
    public function etaToNext(TransportTrip $trip): ?array
    {
        $next = $this->nextStop($trip);
        $last = TripLocation::where('transport_trip_id', $trip->id)->latest('id')->first();

        if (! $next || $next->latitude === null || $next->longitude === null || ! $last) {
            return null;
        }

        $metres = self::distanceMetres($last->latitude, $last->longitude, (float) $next->latitude, (float) $next->longitude) * 1.3;

        $speeds = TripLocation::where('transport_trip_id', $trip->id)->whereNotNull('speed_kmh')->latest('id')->limit(5)->pluck('speed_kmh')->filter(fn ($s) => $s >= 5);
        $kmh = $speeds->isNotEmpty() ? max(10, min(60, $speeds->avg())) : 25;

        return [
            'stop_id' => $next->id, 'name' => $next->name,
            'distance_m' => (int) round($metres),
            'eta_minutes' => (int) max(1, min(999, ceil($metres / 1000 / $kmh * 60))),
        ];
    }

    protected function assertActive(TransportTrip $trip): void
    {
        if (! $trip->fresh()->isActive()) {
            throw ValidationException::withMessages(['trip' => [__('This trip has already ended.')]]);
        }
    }

    /** Great-circle distance in metres. */
    public static function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
