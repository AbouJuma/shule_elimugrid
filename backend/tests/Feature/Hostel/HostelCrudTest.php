<?php

namespace Tests\Feature\Hostel;

use App\Models\AcademicYear;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HostelCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithStudent(string $name = 'Sunrise'): array
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
            'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $student, $admin];
    }

    public function test_admin_can_update_and_delete_an_empty_hostel(): void
    {
        [, , $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/hostel', ['name' => 'Sunrise House', 'gender' => 'mixed']);
        $hostel = Hostel::first();

        $this->actingAs($admin)->put("/hostel/{$hostel->uuid}", ['name' => 'Sunrise House A', 'gender' => 'mixed'])
            ->assertRedirect(route('school-admin.hostel.index'));
        $this->assertSame('Sunrise House A', $hostel->fresh()->name);

        $this->actingAs($admin)->delete("/hostel/{$hostel->uuid}")
            ->assertRedirect(route('school-admin.hostel.index'));
        $this->assertSoftDeleted($hostel);
    }

    public function test_deleting_a_hostel_with_rooms_is_blocked(): void
    {
        [, , $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/hostel', ['name' => 'Sunrise House', 'gender' => 'mixed']);
        $hostel = Hostel::first();
        $this->actingAs($admin)->post('/hostel/rooms', ['hostel_id' => $hostel->id, 'room_number' => 'A-101', 'capacity' => 2]);

        $this->actingAs($admin)->delete("/hostel/{$hostel->uuid}")->assertSessionHasErrors('hostel');
        $this->assertNotSoftDeleted($hostel);
    }

    public function test_admin_can_update_and_delete_an_empty_room(): void
    {
        [, , $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/hostel', ['name' => 'Sunrise House', 'gender' => 'mixed']);
        $hostel = Hostel::first();
        $this->actingAs($admin)->post('/hostel/rooms', ['hostel_id' => $hostel->id, 'room_number' => 'A-101', 'capacity' => 2]);
        $room = HostelRoom::first();

        $this->actingAs($admin)->put("/hostel/rooms/{$room->uuid}", [
            'hostel_id' => $hostel->id, 'room_number' => 'A-102', 'capacity' => 3,
        ])->assertRedirect(route('school-admin.hostel.rooms'));
        $this->assertSame('A-102', $room->fresh()->room_number);

        $this->actingAs($admin)->delete("/hostel/rooms/{$room->uuid}")
            ->assertRedirect(route('school-admin.hostel.rooms'));
        $this->assertSoftDeleted($room);
    }

    public function test_reducing_room_capacity_below_occupancy_is_rejected(): void
    {
        [, $student, $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/hostel', ['name' => 'Sunrise House', 'gender' => 'mixed']);
        $hostel = Hostel::first();
        $this->actingAs($admin)->post('/hostel/rooms', ['hostel_id' => $hostel->id, 'room_number' => 'A-101', 'capacity' => 2]);
        $room = HostelRoom::first();
        $this->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", ['student_id' => $student->id]);

        $response = $this->from('/hostel/rooms')->actingAs($admin)->put("/hostel/rooms/{$room->uuid}", [
            'hostel_id' => $hostel->id, 'room_number' => 'A-101', 'capacity' => 0,
        ]);

        $response->assertSessionHasErrors('capacity');
        $this->assertSame(2, $room->fresh()->capacity);
    }

    public function test_deleting_an_occupied_room_is_blocked(): void
    {
        [, $student, $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/hostel', ['name' => 'Sunrise House', 'gender' => 'mixed']);
        $hostel = Hostel::first();
        $this->actingAs($admin)->post('/hostel/rooms', ['hostel_id' => $hostel->id, 'room_number' => 'A-101', 'capacity' => 2]);
        $room = HostelRoom::first();
        $this->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", ['student_id' => $student->id]);

        $this->actingAs($admin)->delete("/hostel/rooms/{$room->uuid}")->assertSessionHasErrors('room');
        $this->assertNotSoftDeleted($room);
    }
}
