<?php

namespace Tests\Feature\Transport;

use App\Models\AcademicYear;
use App\Models\RouteStop;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportTrip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DefaultRoleProvisioner;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    /** Three stops a few hundred metres apart in Dar es Salaam. */
    protected const STOPS = [
        ['Mikocheni', -6.7924, 39.2083],
        ['Masaki', -6.7960, 39.2130],
        ['Oysterbay', -6.8010, 39.2190],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function user(School $school, string $name, string $role): User
    {
        setPermissionsTeamId($school->id);
        $user = User::create([
            'school_id' => $school->id, 'name' => $name,
            'email' => str($school->slug.'-'.$name)->slug().'@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** @return array{school: School, admin: User, driver: User, vehicle: Vehicle, route: TransportRoute, stops: \Illuminate\Support\Collection} */
    protected function setup_school(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $admin = $this->user($school, 'Admin', 'School Administrator');
        $driver = $this->user($school, 'Driver', 'Driver');

        $vehicle = Vehicle::create(['school_id' => $school->id, 'driver_user_id' => $driver->id, 'registration_number' => 'T 111 AAA', 'capacity' => 30]);
        $route = TransportRoute::create(['school_id' => $school->id, 'vehicle_id' => $vehicle->id, 'name' => 'Route A']);

        $stops = collect(self::STOPS)->map(fn ($s, $i) => RouteStop::create([
            'transport_route_id' => $route->id, 'name' => $s[0], 'sequence' => $i + 1, 'latitude' => $s[1], 'longitude' => $s[2],
        ]));

        return compact('school', 'admin', 'driver', 'vehicle', 'route', 'stops');
    }

    protected function start(array $d, string $direction = 'morning', ?User $as = null)
    {
        Sanctum::actingAs($as ?? $d['driver']);

        return $this->postJson('/api/v1/transport/trips', [
            'vehicle_id' => $d['vehicle']->uuid, 'route_id' => $d['route']->uuid, 'direction' => $direction,
        ]);
    }

    public function test_the_assigned_driver_starts_a_trip_and_gets_the_stops_in_order(): void
    {
        $d = $this->setup_school();

        $r = $this->start($d)->assertCreated();

        $r->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.direction', 'morning')
            ->assertJsonPath('data.route.name', 'Route A')
            ->assertJsonPath('data.progress.total', 3)
            ->assertJsonPath('data.progress.visited', 0)
            ->assertJsonPath('data.stops.0.name', 'Mikocheni')
            ->assertJsonPath('data.stops.0.is_next', true)
            ->assertJsonPath('data.stops.2.name', 'Oysterbay');
    }

    public function test_afternoon_trips_visit_the_stops_in_reverse(): void
    {
        $d = $this->setup_school();

        $this->start($d, 'afternoon')->assertCreated()
            ->assertJsonPath('data.stops.0.name', 'Oysterbay')
            ->assertJsonPath('data.stops.0.is_next', true)
            ->assertJsonPath('data.stops.2.name', 'Mikocheni');
    }

    public function test_only_the_assigned_driver_can_start_and_only_one_trip_runs_per_vehicle(): void
    {
        $d = $this->setup_school();
        $other = $this->user($d['school'], 'Other Driver', 'Driver');

        $this->start($d, 'morning', $other)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $this->start($d, 'morning', $d['admin'])->assertForbidden();

        $this->start($d)->assertCreated();
        $this->start($d)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    }

    public function test_inactive_vehicles_foreign_routes_and_empty_routes_are_refused(): void
    {
        $d = $this->setup_school();

        $d['vehicle']->update(['status' => 'maintenance']);
        $this->start($d)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $d['vehicle']->update(['status' => 'active']);

        $otherVehicle = Vehicle::create(['school_id' => $d['school']->id, 'registration_number' => 'T 222 BBB']);
        $d['route']->update(['vehicle_id' => $otherVehicle->id]);
        $this->start($d)->assertUnprocessable()->assertJsonValidationErrors('route_id');
        $d['route']->update(['vehicle_id' => $d['vehicle']->id]);

        $d['route']->stops()->delete();
        $this->start($d)->assertUnprocessable()->assertJsonValidationErrors('route_id');
    }

    public function test_positions_are_stored_and_move_the_vehicle(): void
    {
        $d = $this->setup_school();
        $trip = TransportTrip::where('id', $this->start($d)->json('data.id') ? 1 : 0)->first() ?? TransportTrip::first();

        Sanctum::actingAs($d['driver']);
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.7900, 'longitude' => 39.2060, 'speed_kmh' => 32.5])->assertOk();
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.7910, 'longitude' => 39.2070])->assertOk();

        $this->assertSame(2, $trip->locations()->count());
        $this->assertEquals(-6.7910, (float) $d['vehicle']->fresh()->last_latitude);

        $detail = $this->getJson("/api/v1/transport/trips/{$trip->uuid}")->assertOk();
        $detail->assertJsonPath('data.position.latitude', -6.791)->assertJsonCount(2, 'data.trail');
    }

    public function test_the_bus_is_marked_arrived_when_it_gets_close_to_the_next_stop_in_order(): void
    {
        $d = $this->setup_school();
        $this->start($d)->assertCreated();
        $trip = TransportTrip::first();
        Sanctum::actingAs($d['driver']);

        // Far from stop 1: nothing happens.
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.7800, 'longitude' => 39.2000])
            ->assertOk()->assertJsonPath('data.arrived_stop', null);

        // Standing at stop 3 while stop 1 is still pending must NOT skip ahead.
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.8010, 'longitude' => 39.2190])
            ->assertOk()->assertJsonPath('data.arrived_stop', null);
        $this->assertSame(0, $trip->visits()->count());

        // ~20 m from stop 1: arrived, and stop 2 becomes next.
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.79225, 'longitude' => 39.20835])
            ->assertOk()->assertJsonPath('data.arrived_stop.name', 'Mikocheni');

        $this->getJson("/api/v1/transport/trips/{$trip->uuid}")
            ->assertJsonPath('data.progress.visited', 1)
            ->assertJsonPath('data.stops.1.is_next', true)
            ->assertJsonPath('data.stops.0.is_next', false);

        $this->assertSame('auto', $trip->visits()->first()->source);
    }

    public function test_the_driver_can_mark_a_stop_by_hand_and_it_is_idempotent(): void
    {
        $d = $this->setup_school();
        $this->start($d)->assertCreated();
        $trip = TransportTrip::first();
        Sanctum::actingAs($d['driver']);

        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/stops/{$d['stops'][0]->id}/arrive")->assertOk()->assertJsonPath('data.progress.visited', 1);
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/stops/{$d['stops'][0]->id}/arrive")->assertOk()->assertJsonPath('data.progress.visited', 1);

        // A stop from some other route is not found.
        $foreign = RouteStop::create([
            'transport_route_id' => TransportRoute::create(['school_id' => $d['school']->id, 'name' => 'Route B'])->id,
            'name' => 'Elsewhere', 'sequence' => 1, 'latitude' => -6.9, 'longitude' => 39.3,
        ]);
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/stops/{$foreign->id}/arrive")->assertNotFound();
    }

    public function test_ending_a_trip_stops_further_updates(): void
    {
        $d = $this->setup_school();
        $this->start($d)->assertCreated();
        $trip = TransportTrip::first();
        Sanctum::actingAs($d['driver']);

        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/end")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($trip->fresh()->ended_at);

        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.79, 'longitude' => 39.2])->assertUnprocessable();
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/end")->assertUnprocessable();

        // The vehicle can start again once the trip is over.
        $this->start($d)->assertCreated();
    }

    public function test_driver_sees_only_their_own_trips_managers_see_all_and_others_see_none(): void
    {
        $d = $this->setup_school();
        $this->start($d)->assertCreated();

        // A second driver with their own bus and trip.
        $other = $this->user($d['school'], 'Second Driver', 'Driver');
        $bus2 = Vehicle::create(['school_id' => $d['school']->id, 'driver_user_id' => $other->id, 'registration_number' => 'T 333 CCC']);
        $route2 = TransportRoute::create(['school_id' => $d['school']->id, 'vehicle_id' => $bus2->id, 'name' => 'Route B']);
        RouteStop::create(['transport_route_id' => $route2->id, 'name' => 'Kariakoo', 'sequence' => 1, 'latitude' => -6.82, 'longitude' => 39.27]);
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/transport/trips', ['vehicle_id' => $bus2->uuid, 'route_id' => $route2->uuid, 'direction' => 'morning'])->assertCreated();

        Sanctum::actingAs($d['admin']);
        $this->getJson('/api/v1/transport/trips/active')->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs($d['driver']);
        $this->getJson('/api/v1/transport/trips/active')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.route.name', 'Route A');
        $this->getJson('/api/v1/transport/trips/current')->assertOk()->assertJsonPath('data.route.name', 'Route A');

        $secondTrip = TransportTrip::where('vehicle_id', $bus2->id)->first();
        $this->getJson("/api/v1/transport/trips/{$secondTrip->uuid}")->assertForbidden();
        $this->postJson("/api/v1/transport/trips/{$secondTrip->uuid}/location", ['latitude' => -6.8, 'longitude' => 39.2])->assertForbidden();
        $this->postJson("/api/v1/transport/trips/{$secondTrip->uuid}/end")->assertForbidden();

        // A role that only has transport.view (the Parent role) cannot watch buses.
        $parent = $this->user($d['school'], 'Parent', 'Parent');
        Sanctum::actingAs($parent);
        $this->getJson('/api/v1/transport/trips/active')->assertForbidden();
        $this->getJson("/api/v1/transport/trips/{$secondTrip->uuid}")->assertForbidden();
    }

    public function test_a_driver_with_no_running_trip_gets_null(): void
    {
        $d = $this->setup_school();
        Sanctum::actingAs($d['driver']);

        $this->getJson('/api/v1/transport/trips/current')->assertOk()->assertJsonPath('data', null);
    }

    public function test_another_school_cannot_see_the_trip(): void
    {
        $d = $this->setup_school('Sunrise');
        $this->start($d)->assertCreated();
        $trip = TransportTrip::first();

        $b = $this->setup_school('Moonlight');
        Sanctum::actingAs($b['admin']);

        $this->getJson("/api/v1/transport/trips/{$trip->uuid}")->assertNotFound();
        $this->getJson('/api/v1/transport/trips/active')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_stops_report_how_many_students_wait_there(): void
    {
        $d = $this->setup_school();
        $year = AcademicYear::create(['school_id' => $d['school']->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $d['school']->id, 'name' => 'Form 1']);

        foreach ([1, 2] as $n) {
            Student::create([
                'school_id' => $d['school']->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
                'admission_number' => "S-$n", 'first_name' => "Kid$n", 'last_name' => 'Test', 'gender' => 'male',
                'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
                'transport_route_id' => $d['route']->id, 'route_stop_id' => $d['stops'][1]->id,
            ]);
        }

        $this->start($d)->assertCreated()
            ->assertJsonPath('data.stops.0.students', 0)
            ->assertJsonPath('data.stops.1.students', 2);
    }

    public function test_the_next_stop_comes_with_a_distance_and_an_eta(): void
    {
        $d = $this->setup_school();
        $this->start($d)->assertCreated()->assertJsonPath('data.next', null); // no position yet, so no estimate
        $trip = TransportTrip::first();
        Sanctum::actingAs($d['driver']);

        // ~1.1 km from stop 1 (0.01° of latitude), moving at 30 km/h.
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.8024, 'longitude' => 39.2083, 'speed_kmh' => 30])->assertOk();

        $next = $this->getJson("/api/v1/transport/trips/{$trip->uuid}")->assertOk()->json('data.next');

        $this->assertSame('Mikocheni', $next['name']);
        $this->assertEqualsWithDelta(1440, $next['distance_m'], 60);      // 1.1 km straight × 1.3 for the roads
        $this->assertSame(3, $next['eta_minutes']);                       // 1.44 km at 30 km/h ≈ 2.9 min → 3

        // A parked bus (no useful speed) is assumed to travel at 25 km/h.
        TransportTrip::first()->locations()->delete();
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.8024, 'longitude' => 39.2083, 'speed_kmh' => 0])->assertOk();
        $this->assertSame(4, $this->getJson("/api/v1/transport/trips/{$trip->uuid}")->json('data.next.eta_minutes')); // 1.44 km at 25 km/h ≈ 3.5 min → 4
    }

    public function test_the_eta_disappears_once_the_trip_has_ended(): void
    {
        $d = $this->setup_school();
        $this->start($d)->assertCreated();
        $trip = TransportTrip::first();
        Sanctum::actingAs($d['driver']);
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/location", ['latitude' => -6.80, 'longitude' => 39.20])->assertOk();
        $this->postJson("/api/v1/transport/trips/{$trip->uuid}/end")->assertOk()->assertJsonPath('data.next', null);
    }

    public function test_the_distance_helper_is_accurate(): void
    {
        // 0.001° of latitude is ~111 m.
        $this->assertEqualsWithDelta(111, TripService::distanceMetres(-6.79, 39.2, -6.791, 39.2), 2);
        $this->assertSame(0.0, TripService::distanceMetres(-6.79, 39.2, -6.79, 39.2));
    }
}
