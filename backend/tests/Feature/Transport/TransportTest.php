<?php

namespace Tests\Feature\Transport;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-{$name}-1", 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
        ]);

        setPermissionsTeamId($school->id);

        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        $driver = User::create([
            'school_id' => $school->id, 'name' => "{$name} Driver",
            'email' => str($name)->slug().'-driver@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $driver->assignRole('Driver');

        return [$school, $student, $admin, $driver];
    }

    public function test_admin_can_create_a_vehicle_and_assign_a_driver(): void
    {
        [$school, $student, $admin, $driver] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'KDA 123A',
            'make' => 'Toyota',
            'model' => 'Coaster',
            'capacity' => 30,
            'driver_user_id' => $driver->id,
        ])->assertCreated();

        $response->assertJsonPath('data.registration_number', 'KDA 123A');
        $response->assertJsonPath('data.driver.name', 'Sunrise Driver');
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_registration_number_must_be_unique_per_school(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/vehicles', ['registration_number' => 'KDA 123A'])->assertCreated();
        $this->postJson('/api/v1/vehicles', ['registration_number' => 'KDA 123A'])->assertUnprocessable();
    }

    public function test_admin_can_create_a_route_with_stops_and_assign_a_student(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $vehicleId = $this->postJson('/api/v1/vehicles', ['registration_number' => 'KDA 123A'])->json('data.id');

        $routeResponse = $this->postJson('/api/v1/transport-routes', [
            'name' => 'Route A - Town',
            'vehicle_id' => \App\Models\Vehicle::where('uuid', $vehicleId)->value('id'),
            'stops' => [
                ['name' => 'Town Stage', 'sequence' => 1, 'latitude' => -1.2833, 'longitude' => 36.8167],
                ['name' => 'Estate Gate', 'sequence' => 2, 'latitude' => -1.29, 'longitude' => 36.82],
            ],
        ])->assertCreated();

        $routeResponse->assertJsonPath('data.name', 'Route A - Town');
        $routeResponse->assertJsonCount(2, 'data.stops');
        $routeUuid = $routeResponse->json('data.id');
        $stopId = $routeResponse->json('data.stops.0.id');

        $studentUuid = $student->uuid;
        $assignResponse = $this->patchJson("/api/v1/students/{$studentUuid}/transport", [
            'transport_route_id' => \App\Models\TransportRoute::where('uuid', $routeUuid)->value('id'),
            'route_stop_id' => $stopId,
        ])->assertOk();

        $assignResponse->assertJsonPath('data.transport_route.name', 'Route A - Town');
        $assignResponse->assertJsonPath('data.route_stop.name', 'Town Stage');
    }

    public function test_driver_can_update_their_own_vehicles_location(): void
    {
        [$school, $student, $admin, $driver] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $vehicleUuid = $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'KDA 123A', 'driver_user_id' => $driver->id,
        ])->json('data.id');

        Sanctum::actingAs($driver);
        $response = $this->postJson("/api/v1/vehicles/{$vehicleUuid}/location", [
            'latitude' => -1.2833, 'longitude' => 36.8167,
        ])->assertOk();

        $response->assertJsonPath('data.last_location.latitude', -1.2833);
        $response->assertJsonPath('data.last_location.longitude', 36.8167);
    }

    public function test_driver_cannot_update_a_vehicle_they_are_not_assigned_to(): void
    {
        [$school, $student, $admin, $driver] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $vehicleUuid = $this->postJson('/api/v1/vehicles', ['registration_number' => 'KDA 123A'])
            ->json('data.id');

        Sanctum::actingAs($driver);
        $this->postJson("/api/v1/vehicles/{$vehicleUuid}/location", [
            'latitude' => -1.2833, 'longitude' => 36.8167,
        ])->assertForbidden();
    }

    public function test_teacher_cannot_manage_vehicles_or_track_location(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-transport@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        Sanctum::actingAs($teacher);
        $this->postJson('/api/v1/vehicles', ['registration_number' => 'KDA 999Z'])->assertForbidden();
    }

    public function test_school_b_cannot_see_or_update_school_as_vehicle(): void
    {
        [$schoolA, , $adminA] = $this->makeSchool('School A');
        [, , $adminB, $driverB] = $this->makeSchool('School B');

        Sanctum::actingAs($adminA);
        $vehicleUuid = $this->postJson('/api/v1/vehicles', ['registration_number' => 'KDA 123A'])
            ->json('data.id');

        Sanctum::actingAs($adminB);
        $this->getJson("/api/v1/vehicles/{$vehicleUuid}")->assertNotFound();

        Sanctum::actingAs($driverB);
        $this->postJson("/api/v1/vehicles/{$vehicleUuid}/location", [
            'latitude' => -1.28, 'longitude' => 36.81,
        ])->assertNotFound();
    }
}
