<?php

namespace Tests\Feature\Transport;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VehicleRouteCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_admin_can_delete_a_vehicle(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/vehicles', ['registration_number' => 'T123ABC', 'capacity' => 30]);
        $vehicle = Vehicle::first();

        $this->actingAs($admin)->delete("/vehicles/{$vehicle->uuid}")
            ->assertRedirect(route('school-admin.vehicles.index'));
        $this->assertSoftDeleted($vehicle);
    }

    public function test_admin_can_delete_a_route_with_no_students(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/transport-routes', ['name' => 'Route A']);
        $route = TransportRoute::first();

        $this->actingAs($admin)->delete("/transport-routes/{$route->uuid}")
            ->assertRedirect(route('school-admin.transport-routes.index'));
        $this->assertSoftDeleted($route);
    }

    public function test_deleting_a_route_with_assigned_students_is_blocked(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/transport-routes', ['name' => 'Route A']);
        $route = TransportRoute::first();

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'transport_route_id' => $route->id,
            'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        $this->actingAs($admin)->delete("/transport-routes/{$route->uuid}")->assertSessionHasErrors('route');
        $this->assertNotSoftDeleted($route);
    }
}
