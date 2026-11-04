<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\RouteStop;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DefaultRoleProvisioner;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The create / edit / delete calls the mobile app makes for transport, guardians and students. */
class MobileCrudTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $this->school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
    }

    protected function user(string $name, string $role, ?School $school = null): User
    {
        $school ??= $this->school;
        setPermissionsTeamId($school->id);
        $user = User::create(['school_id' => $school->id, 'name' => $name, 'email' => str($school->slug.$name)->slug().'@test.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_a_vehicle_can_be_created_and_edited_using_the_drivers_uuid(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $driver = $this->user('Driver', 'Driver');
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/v1/vehicles', ['registration_number' => 'T 123 ABC', 'make' => 'Toyota', 'capacity' => 30, 'driver_user_id' => $driver->uuid])
            ->assertCreated()->assertJsonPath('data.driver.name', 'Driver');

        $vehicleUuid = $created->json('data.id');
        $this->assertSame($driver->id, Vehicle::where('uuid', $vehicleUuid)->value('driver_user_id'));

        $this->putJson("/api/v1/vehicles/$vehicleUuid", ['registration_number' => 'T 123 ABC', 'status' => 'maintenance', 'driver_user_id' => null])
            ->assertOk()->assertJsonPath('data.status', 'maintenance')->assertJsonPath('data.driver', null);
    }

    public function test_a_uuid_from_another_school_is_not_accepted(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $other = School::create(['name' => 'Moonlight', 'slug' => 'moonlight', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($other);
        $foreignDriver = $this->user('Foreign', 'Driver', $other);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/vehicles', ['registration_number' => 'T 999 ZZZ', 'driver_user_id' => $foreignDriver->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('driver_user_id');
    }

    public function test_a_route_with_stops_can_be_created_with_the_vehicle_uuid_and_edited_without_losing_students(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $bus = Vehicle::create(['school_id' => $this->school->id, 'registration_number' => 'T 111 AAA'])->refresh();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/v1/transport-routes', [
            'name' => 'Route A', 'vehicle_id' => $bus->uuid,
            'stops' => [
                ['name' => 'Kawe', 'sequence' => 1, 'latitude' => -6.73, 'longitude' => 39.22, 'pickup_time' => '06:30'],
                ['name' => 'Mwenge', 'sequence' => 2, 'latitude' => -6.744, 'longitude' => 39.228],
            ],
        ])->assertCreated();

        $route = TransportRoute::where('uuid', $created->json('data.id'))->firstOrFail();
        $this->assertSame($bus->id, $route->vehicle_id);

        // A student waits at stop 2.
        $stops = $route->stops()->get();
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $kid = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'S-1', 'first_name' => 'Kid', 'last_name' => 'One', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'transport_route_id' => $route->id, 'route_stop_id' => $stops[1]->id]);

        // Edit: rename stop 1, keep stop 2 (by id), add a third, drop nothing.
        $this->putJson("/api/v1/transport-routes/{$route->uuid}", [
            'name' => 'Route A (new)', 'status' => 'active', 'vehicle_id' => $bus->uuid,
            'stops' => [
                ['id' => $stops[0]->id, 'name' => 'Kawe Roundabout', 'sequence' => 1, 'latitude' => -6.73, 'longitude' => 39.22],
                ['id' => $stops[1]->id, 'name' => 'Mwenge', 'sequence' => 2, 'latitude' => -6.744, 'longitude' => 39.228],
                ['name' => 'Mikocheni', 'sequence' => 3, 'latitude' => -6.761, 'longitude' => 39.242],
            ],
        ])->assertOk()->assertJsonCount(3, 'data.stops');

        $this->assertSame($stops[1]->id, $kid->fresh()->route_stop_id, 'the student keeps their stop after the route is edited');
        $this->assertSame('Kawe Roundabout', RouteStop::find($stops[0]->id)->name);

        // Removing a stop from the payload deletes only that stop.
        $this->putJson("/api/v1/transport-routes/{$route->uuid}", [
            'name' => 'Route A (new)', 'status' => 'active',
            'stops' => [['id' => $stops[0]->id, 'name' => 'Kawe Roundabout', 'sequence' => 1]],
        ])->assertOk()->assertJsonCount(1, 'data.stops');
    }

    public function test_students_can_be_assigned_to_a_route_by_uuid(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $route = TransportRoute::create(['school_id' => $this->school->id, 'name' => 'Route A']);
        $stop = RouteStop::create(['transport_route_id' => $route->id, 'name' => 'Kawe', 'sequence' => 1]);
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $kid = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'S-1', 'first_name' => 'Kid', 'last_name' => 'One', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15']);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/students/{$kid->uuid}/transport", ['transport_route_id' => $route->uuid, 'route_stop_id' => $stop->id])
            ->assertOk()->assertJsonPath('data.transport_route.name', 'Route A');
    }

    public function test_vehicles_and_routes_can_be_deleted_unless_in_use(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $driver = $this->user('Driver', 'Driver');
        $bus = Vehicle::create(['school_id' => $this->school->id, 'driver_user_id' => $driver->id, 'registration_number' => 'T 111 AAA'])->refresh();
        $route = TransportRoute::create(['school_id' => $this->school->id, 'vehicle_id' => $bus->id, 'name' => 'Route A'])->refresh();
        RouteStop::create(['transport_route_id' => $route->id, 'name' => 'Kawe', 'sequence' => 1, 'latitude' => -6.73, 'longitude' => 39.22]);

        app(TripService::class)->start($this->school, $driver, $bus, $route, 'morning');

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/vehicles/{$bus->uuid}")->assertUnprocessable()->assertJsonValidationErrors('vehicle');
        $this->deleteJson("/api/v1/transport-routes/{$route->uuid}")->assertUnprocessable()->assertJsonValidationErrors('route');

        \App\Models\TransportTrip::first()->update(['status' => 'completed']);

        $this->deleteJson("/api/v1/transport-routes/{$route->uuid}")->assertOk();
        $this->assertNull(TransportRoute::find($route->id));
        $this->deleteJson("/api/v1/vehicles/{$bus->uuid}")->assertOk();
        $this->assertNull(Vehicle::find($bus->id));

        // A route with students cannot be deleted.
        $route2 = TransportRoute::create(['school_id' => $this->school->id, 'name' => 'Route B']);
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'S-1', 'first_name' => 'Kid', 'last_name' => 'One', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'transport_route_id' => $route2->id]);
        $this->deleteJson("/api/v1/transport-routes/{$route2->uuid}")->assertUnprocessable();
    }

    public function test_drivers_and_teachers_cannot_manage_the_fleet(): void
    {
        $driver = $this->user('Driver', 'Driver');
        $teacher = $this->user('Teacher', 'Teacher');
        $bus = Vehicle::create(['school_id' => $this->school->id, 'registration_number' => 'T 111 AAA'])->refresh();

        foreach ([$driver, $teacher] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/v1/vehicles', ['registration_number' => 'T 555 XYZ'])->assertForbidden();
            $this->deleteJson("/api/v1/vehicles/{$bus->uuid}")->assertForbidden();
        }
    }

    public function test_a_guardian_can_be_edited(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Old Name', 'phone' => '0700000000']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/guardians/{$guardian->id}", ['name' => 'New Name', 'phone' => '0711111111', 'email' => 'g@test.local', 'occupation' => 'Farmer'])
            ->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.occupation', 'Farmer');

        $this->putJson("/api/v1/guardians/{$guardian->id}", ['name' => '', 'phone' => ''])->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);

        // Someone without guardians.manage cannot.
        Sanctum::actingAs($this->user('Driver', 'Driver'));
        $this->putJson("/api/v1/guardians/{$guardian->id}", ['name' => 'Hacked', 'phone' => '0'])->assertForbidden();
    }
}
