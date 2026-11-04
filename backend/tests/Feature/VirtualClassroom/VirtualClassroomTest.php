<?php

namespace Tests\Feature\VirtualClassroom;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\VirtualClass;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VirtualClassroomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithClass(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        $teacher = User::create([
            'school_id' => $school->id, 'name' => "{$name} Teacher",
            'email' => str($name)->slug().'-teacher@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        return [$school, $class, $admin, $teacher];
    }

    protected function scheduleData(SchoolClass $class = null): array
    {
        return [
            'title' => 'Algebra Revision',
            'description' => 'Covering quadratics',
            'school_class_id' => $class?->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 45,
        ];
    }

    public function test_admin_can_schedule_a_session_with_a_unique_room(): void
    {
        [, $class, $admin] = $this->makeSchoolWithClass();

        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData($class))
            ->assertRedirect(route('school-admin.virtual-classroom.index'));

        $virtualClass = VirtualClass::first();
        $this->assertSame('Algebra Revision', $virtualClass->title);
        $this->assertSame('scheduled', $virtualClass->status);
        $this->assertStringStartsWith('elimugrid-', $virtualClass->room_name);
        $this->assertSame(
            'https://meet.jit.si/'.$virtualClass->room_name,
            $virtualClass->joinUrl()
        );
    }

    public function test_teacher_can_schedule_a_session(): void
    {
        [, $class, , $teacher] = $this->makeSchoolWithClass();

        $this->actingAs($teacher)->post('/virtual-classroom', $this->scheduleData($class))
            ->assertRedirect(route('school-admin.virtual-classroom.index'));

        $this->assertDatabaseHas('virtual_classes', ['title' => 'Algebra Revision']);
    }

    public function test_scheduling_in_the_past_is_rejected(): void
    {
        [, $class, $admin] = $this->makeSchoolWithClass();

        $data = $this->scheduleData($class);
        $data['scheduled_at'] = now()->subDay()->format('Y-m-d\TH:i');

        $this->from('/virtual-classroom')->actingAs($admin)->post('/virtual-classroom', $data)
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_admin_can_update_a_session(): void
    {
        [, $class, $admin] = $this->makeSchoolWithClass();
        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData($class));
        $virtualClass = VirtualClass::first();

        $data = $this->scheduleData($class);
        $data['title'] = 'Algebra Revision (Updated)';
        $this->actingAs($admin)->put("/virtual-classroom/{$virtualClass->uuid}", $data)
            ->assertRedirect(route('school-admin.virtual-classroom.index'));

        $this->assertSame('Algebra Revision (Updated)', $virtualClass->fresh()->title);
    }

    public function test_deleting_an_upcoming_session_without_cancelling_first_is_blocked(): void
    {
        [, $class, $admin] = $this->makeSchoolWithClass();
        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData($class));
        $virtualClass = VirtualClass::first();

        $response = $this->from('/virtual-classroom')->actingAs($admin)->delete("/virtual-classroom/{$virtualClass->uuid}");

        $response->assertSessionHasErrors('virtual_class');
        $this->assertNotSoftDeleted($virtualClass);
    }

    public function test_admin_can_delete_a_cancelled_session(): void
    {
        [, $class, $admin] = $this->makeSchoolWithClass();
        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData($class));
        $virtualClass = VirtualClass::first();
        $this->actingAs($admin)->patch("/virtual-classroom/{$virtualClass->uuid}/cancel");

        $this->actingAs($admin)->delete("/virtual-classroom/{$virtualClass->uuid}")
            ->assertRedirect(route('school-admin.virtual-classroom.index'));
        $this->assertSoftDeleted($virtualClass);
    }

    public function test_admin_can_cancel_a_session(): void
    {
        [, $class, $admin] = $this->makeSchoolWithClass();
        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData($class));
        $virtualClass = VirtualClass::first();

        $this->actingAs($admin)->patch("/virtual-classroom/{$virtualClass->uuid}/cancel")
            ->assertRedirect(route('school-admin.virtual-classroom.index'));

        $this->assertSame('cancelled', $virtualClass->fresh()->status);
    }

    public function test_cancelling_an_already_cancelled_session_is_rejected(): void
    {
        [, $class, $admin] = $this->makeSchoolWithClass();
        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData($class));
        $virtualClass = VirtualClass::first();
        $this->actingAs($admin)->patch("/virtual-classroom/{$virtualClass->uuid}/cancel");

        $response = $this->from('/virtual-classroom')->actingAs($admin)
            ->patch("/virtual-classroom/{$virtualClass->uuid}/cancel");

        $response->assertSessionHasErrors('status');
    }

    public function test_sending_invites_requires_a_linked_class(): void
    {
        [, , $admin] = $this->makeSchoolWithClass();
        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData(null));
        $virtualClass = VirtualClass::first();

        $response = $this->from('/virtual-classroom')->actingAs($admin)
            ->post("/virtual-classroom/{$virtualClass->uuid}/invites");

        $response->assertSessionHas('status', 'This session is not linked to a class, so there is no one to invite.');
    }

    public function test_sending_invites_messages_every_guardian_in_the_class(): void
    {
        [$school, $class, $admin] = $this->makeSchoolWithClass();

        $year = AcademicYear::where('school_id', $school->id)->first();
        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);
        $guardian = Guardian::create(['school_id' => $school->id, 'name' => 'Jane Doe', 'phone' => '0712345678']);
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        Queue::fake();
        $this->actingAs($admin)->post('/virtual-classroom', $this->scheduleData($class));
        $virtualClass = VirtualClass::first();

        $this->actingAs($admin)->post("/virtual-classroom/{$virtualClass->uuid}/invites")
            ->assertRedirect(route('school-admin.virtual-classroom.index'));

        $this->assertDatabaseHas('sms_logs', [
            'school_id' => $school->id, 'student_id' => $student->id, 'category' => 'general',
        ]);
    }

    public function test_accountant_cannot_manage_virtual_classes(): void
    {
        [$school] = $this->makeSchoolWithClass();

        setPermissionsTeamId($school->id);
        $accountant = User::create([
            'school_id' => $school->id, 'name' => 'Accountant', 'email' => 'accountant-vc@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $accountant->assignRole('Accountant');

        $this->actingAs($accountant)->post('/virtual-classroom', $this->scheduleData(null))->assertForbidden();
    }

    public function test_school_b_cannot_see_school_as_sessions(): void
    {
        [, $classA, $adminA] = $this->makeSchoolWithClass('School A');
        [, , $adminB] = $this->makeSchoolWithClass('School B');

        $this->actingAs($adminA)->post('/virtual-classroom', $this->scheduleData($classA));
        session()->flush();

        $response = $this->actingAs($adminB)->get('/virtual-classroom');
        $response->assertOk();
        $response->assertDontSee('Algebra Revision');
    }
}
