<?php

namespace Tests\Feature\Academics;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AcademicSetupTest extends TestCase
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

    public function test_admin_can_create_several_classes_in_one_submission(): void
    {
        [, $admin] = $this->makeSchool();

        $this->actingAs($admin)->post('/academics/classes', [
            'classes' => [
                ['name' => 'Form 1', 'order' => 1],
                ['name' => 'Form 2', 'order' => 2],
                ['name' => 'Form 3', 'order' => 3],
            ],
        ])->assertRedirect(route('school-admin.academics.index', ['tab' => 'classes']));

        $this->assertSame(3, SchoolClass::count());
        $this->assertDatabaseHas('school_classes', ['name' => 'Form 2', 'order' => 2]);
    }

    public function test_duplicate_names_within_the_same_batch_are_rejected(): void
    {
        [, $admin] = $this->makeSchool();

        $response = $this->from('/academics?tab=classes')->actingAs($admin)->post('/academics/classes', [
            'classes' => [
                ['name' => 'Form 1'],
                ['name' => 'Form 1'],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0, SchoolClass::count());
    }

    public function test_admin_can_update_and_delete_a_class(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/academics/classes', ['classes' => [['name' => 'Form 1', 'order' => 1]]]);
        $class = SchoolClass::first();

        $this->actingAs($admin)->put("/academics/classes/{$class->id}", ['name' => 'Form 1A', 'order' => 5])
            ->assertRedirect(route('school-admin.academics.index', ['tab' => 'classes']));
        $this->assertSame('Form 1A', $class->fresh()->name);

        $this->actingAs($admin)->delete("/academics/classes/{$class->id}")
            ->assertRedirect(route('school-admin.academics.index', ['tab' => 'classes']));
        $this->assertSoftDeleted($class);
    }

    public function test_deleting_a_class_with_enrolled_students_is_blocked(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/academics/classes', ['classes' => [['name' => 'Form 1']]]);
        $class = SchoolClass::first();

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        $this->actingAs($admin)->delete("/academics/classes/{$class->id}")->assertSessionHasErrors('class');
        $this->assertNotSoftDeleted($class);
    }

    public function test_admin_can_create_several_streams_in_one_submission_and_update_and_delete(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/academics/classes', ['classes' => [['name' => 'Form 1']]]);
        $class = SchoolClass::first();

        $this->actingAs($admin)->post('/academics/streams', [
            'streams' => [
                ['school_class_id' => $class->id, 'name' => 'Blue', 'capacity' => 30, 'sort_order' => 1],
                ['school_class_id' => $class->id, 'name' => 'Green', 'capacity' => 30, 'sort_order' => 2],
            ],
        ])->assertRedirect(route('school-admin.academics.index', ['tab' => 'streams']));

        $this->assertSame(2, Stream::count());
        $stream = Stream::where('name', 'Blue')->first();

        $this->actingAs($admin)->put("/academics/streams/{$stream->id}", [
            'school_class_id' => $class->id, 'name' => 'Red', 'capacity' => 25, 'sort_order' => 1,
        ])->assertRedirect(route('school-admin.academics.index', ['tab' => 'streams']));
        $this->assertSame('Red', $stream->fresh()->name);

        $this->actingAs($admin)->delete("/academics/streams/{$stream->id}")
            ->assertRedirect(route('school-admin.academics.index', ['tab' => 'streams']));
        $this->assertSoftDeleted($stream);
    }

    public function test_class_page_shows_streams_with_student_totals_and_filters_students(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/academics/classes', ['classes' => [['name' => 'Form 1']]]);
        $class = SchoolClass::first();
        $blue = Stream::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Blue', 'capacity' => 2]);
        $green = Stream::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Green']);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        foreach ([['Amina', 'female', $blue], ['Baraka', 'male', $blue], ['Chausiku', 'female', $green], ['Dawa', 'male', null]] as $i => [$name, $gender, $stream]) {
            Student::create([
                'school_id' => $school->id, 'school_class_id' => $class->id, 'stream_id' => $stream?->id, 'academic_year_id' => $year->id,
                'admission_number' => 'STU-'.$i, 'first_name' => $name, 'last_name' => 'Test', 'gender' => $gender,
                'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
            ]);
        }

        $this->actingAs($admin)->get("/academics/classes/{$class->id}")
            ->assertOk()
            ->assertViewHas('totals', ['all' => 4, 'active' => 4, 'male' => 2, 'female' => 2])
            ->assertViewHas('streamRows', fn ($rows) => $rows->count() === 3 && $rows->firstWhere('name', 'Blue')['total'] === 2);

        $this->actingAs($admin)->get("/academics/classes/{$class->id}?stream_id={$green->id}")
            ->assertOk()->assertSee('Chausiku Test')->assertDontSee('Amina Test');

        $this->actingAs($admin)->get("/academics/classes/{$class->id}?stream_id=none")
            ->assertOk()->assertSee('Dawa Test')->assertDontSee('Amina Test');
    }

    public function test_moving_a_class_swaps_order_with_its_neighbour(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/academics/classes', [
            'classes' => [['name' => 'Form 1', 'order' => 1], ['name' => 'Form 2', 'order' => 2]],
        ]);
        $form1 = SchoolClass::where('name', 'Form 1')->first();
        $form2 = SchoolClass::where('name', 'Form 2')->first();

        $this->actingAs($admin)->patch("/academics/classes/{$form1->id}/move/down");

        $this->assertSame(2, $form1->fresh()->order);
        $this->assertSame(1, $form2->fresh()->order);
    }

    public function test_teacher_cannot_manage_classes(): void
    {
        [$school] = $this->makeSchool();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-academics@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->post('/academics/classes', ['classes' => [['name' => 'Form 1']]])
            ->assertForbidden();
    }

    public function test_school_b_cannot_see_school_as_classes(): void
    {
        [, $adminA] = $this->makeSchool('School A');
        [, $adminB] = $this->makeSchool('School B');

        $this->actingAs($adminA)->post('/academics/classes', ['classes' => [['name' => 'Only In A']]]);
        session()->flush();

        $response = $this->actingAs($adminB)->get('/academics?tab=classes');
        $response->assertOk();
        $response->assertDontSee('Only In A');
    }
}
