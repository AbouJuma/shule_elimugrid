<?php

namespace Tests\Feature\Hostel;

use App\Models\AcademicYear;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HostelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithStudents(string $name = 'Sunrise', int $count = 2): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        $students = collect(range(1, $count))->map(fn ($i) => Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-{$name}-{$i}", 'first_name' => "S{$i}", 'last_name' => 'Test',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]));

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin, $students];
    }

    protected function makeHostelWithRoom($admin, int $capacity = 1): HostelRoom
    {
        $this->actingAs($admin)->post('/hostel', ['name' => 'Sunrise House', 'gender' => 'mixed']);
        $hostelId = \App\Models\Hostel::first()->id;

        $this->actingAs($admin)->post('/hostel/rooms', [
            'hostel_id' => $hostelId, 'room_number' => 'A-101', 'capacity' => $capacity,
        ]);

        return HostelRoom::first();
    }

    public function test_admin_can_add_a_hostel_and_a_room(): void
    {
        [, $admin] = $this->makeSchoolWithStudents();

        $room = $this->makeHostelWithRoom($admin, capacity: 2);

        $this->assertDatabaseHas('hostels', ['name' => 'Sunrise House']);
        $this->assertSame('A-101', $room->room_number);
        $this->assertSame(2, $room->capacity);
        $this->assertSame(0, $room->occupied_count);
    }

    public function test_allocating_a_student_increments_occupied_count(): void
    {
        [, $admin, $students] = $this->makeSchoolWithStudents();
        $room = $this->makeHostelWithRoom($admin, capacity: 2);

        $this->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", [
            'student_id' => $students[0]->id,
        ])->assertRedirect(route('school-admin.hostel.rooms'));

        $this->assertSame(1, $room->fresh()->occupied_count);
        $this->assertDatabaseHas('hostel_allocations', [
            'hostel_room_id' => $room->id, 'student_id' => $students[0]->id, 'status' => 'active',
        ]);
    }

    public function test_allocating_to_a_full_room_is_rejected(): void
    {
        [, $admin, $students] = $this->makeSchoolWithStudents(count: 2);
        $room = $this->makeHostelWithRoom($admin, capacity: 1);

        $this->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", ['student_id' => $students[0]->id]);

        $response = $this->from('/hostel/rooms')->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", [
            'student_id' => $students[1]->id,
        ]);

        $response->assertSessionHasErrors('hostel_room_id');
        $this->assertSame(1, $room->fresh()->occupied_count);
    }

    public function test_a_student_cannot_be_allocated_to_two_rooms_at_once(): void
    {
        [, $admin, $students] = $this->makeSchoolWithStudents();
        $roomA = $this->makeHostelWithRoom($admin, capacity: 2);

        $this->actingAs($admin)->post('/hostel/rooms', [
            'hostel_id' => $roomA->hostel_id, 'room_number' => 'A-102', 'capacity' => 2,
        ]);
        $roomB = HostelRoom::where('room_number', 'A-102')->first();

        $this->actingAs($admin)->post("/hostel/rooms/{$roomA->uuid}/allocate", ['student_id' => $students[0]->id]);

        $response = $this->from('/hostel/rooms')->actingAs($admin)->post("/hostel/rooms/{$roomB->uuid}/allocate", [
            'student_id' => $students[0]->id,
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertSame(0, $roomB->fresh()->occupied_count);
    }

    public function test_vacating_decrements_occupied_count_and_frees_the_student(): void
    {
        [, $admin, $students] = $this->makeSchoolWithStudents();
        $room = $this->makeHostelWithRoom($admin, capacity: 1);

        $this->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", ['student_id' => $students[0]->id]);
        $allocation = HostelAllocation::first();

        $this->actingAs($admin)->patch("/hostel/allocations/{$allocation->uuid}/vacate")
            ->assertRedirect(route('school-admin.hostel.allocations'));

        $this->assertSame(0, $room->fresh()->occupied_count);
        $this->assertSame('vacated', $allocation->fresh()->status);
        $this->assertNotNull($allocation->fresh()->vacated_date);

        // The student is now free to be allocated elsewhere.
        $this->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", ['student_id' => $students[0]->id])
            ->assertRedirect(route('school-admin.hostel.rooms'));
        $this->assertSame(1, $room->fresh()->occupied_count);
    }

    public function test_vacating_an_already_vacated_allocation_is_rejected(): void
    {
        [, $admin, $students] = $this->makeSchoolWithStudents();
        $room = $this->makeHostelWithRoom($admin);

        $this->actingAs($admin)->post("/hostel/rooms/{$room->uuid}/allocate", ['student_id' => $students[0]->id]);
        $allocation = HostelAllocation::first();
        $this->actingAs($admin)->patch("/hostel/allocations/{$allocation->uuid}/vacate");

        $response = $this->from('/hostel/allocations')->actingAs($admin)
            ->patch("/hostel/allocations/{$allocation->uuid}/vacate");

        $response->assertSessionHasErrors('status');
    }

    public function test_teacher_cannot_manage_the_hostel(): void
    {
        [$school] = $this->makeSchoolWithStudents();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-hostel@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->post('/hostel', ['name' => 'X', 'gender' => 'mixed'])->assertForbidden();
    }

    public function test_school_b_cannot_see_school_as_hostels(): void
    {
        [, $adminA] = $this->makeSchoolWithStudents('School A');
        [, $adminB] = $this->makeSchoolWithStudents('School B');

        $this->actingAs($adminA)->post('/hostel', ['name' => 'Only In A', 'gender' => 'mixed']);
        session()->flush();

        $response = $this->actingAs($adminB)->get('/hostel');
        $response->assertOk();
        $response->assertDontSee('Only In A');
    }
}
