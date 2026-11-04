<?php

namespace Tests\Feature\Timetable;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TimetableTest extends TestCase
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

        $class1 = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $class2 = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 2']);
        $blue = Stream::create(['school_id' => $school->id, 'school_class_id' => $class1->id, 'name' => 'Blue']);
        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $eng = Subject::create(['school_id' => $school->id, 'name' => 'English']);
        $p1 = TimetablePeriod::create(['school_id' => $school->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'sort_order' => 1]);
        $brk = TimetablePeriod::create(['school_id' => $school->id, 'name' => 'Break', 'start_time' => '10:00', 'end_time' => '10:20', 'is_break' => true, 'sort_order' => 2]);

        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => str($name)->slug().'-a@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');
        $teacher = User::create(['school_id' => $school->id, 'name' => 'Mr Juma', 'email' => str($name)->slug().'-t@test.local', 'password' => 'password', 'status' => 'active']);
        $teacher->assignRole('Teacher');

        return compact('school', 'class1', 'class2', 'blue', 'math', 'eng', 'p1', 'brk', 'admin', 'teacher');
    }

    protected function slot(array $d, array $override = []): array
    {
        return [
            'school_class_id' => $d['class1']->id, 'day_of_week' => 1, 'timetable_period_id' => $d['p1']->id,
            'subject_id' => $d['math']->id, 'teacher_user_id' => $d['teacher']->id, ...$override,
        ];
    }

    public function test_admin_can_schedule_and_replace_a_lesson(): void
    {
        $d = $this->makeSchool();

        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d, ['room' => 'R1']))->assertRedirect();
        $this->assertSame(1, TimetableEntry::count());

        // Same slot again replaces rather than duplicates.
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d, ['subject_id' => $d['eng']->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, TimetableEntry::count());
        $this->assertSame($d['eng']->id, TimetableEntry::first()->subject_id);
    }

    public function test_a_teacher_cannot_be_in_two_classes_at_once(): void
    {
        $d = $this->makeSchool();
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d));

        $this->from('/timetable')->actingAs($d['admin'])
            ->post('/timetable/entries', $this->slot($d, ['school_class_id' => $d['class2']->id]))
            ->assertSessionHasErrors('teacher_user_id');

        $this->assertSame(1, TimetableEntry::count());
    }

    public function test_different_teachers_can_teach_different_classes_in_the_same_slot(): void
    {
        $d = $this->makeSchool();
        setPermissionsTeamId($d['school']->id);
        $other = User::create(['school_id' => $d['school']->id, 'name' => 'Ms Neema', 'email' => 'neema@test.local', 'password' => 'password', 'status' => 'active']);
        $other->assignRole('Teacher');

        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d));
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d, ['school_class_id' => $d['class2']->id, 'teacher_user_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TimetableEntry::count());
    }

    public function test_a_whole_class_lesson_conflicts_with_a_stream_lesson_in_the_same_slot(): void
    {
        $d = $this->makeSchool();
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d, ['teacher_user_id' => null]));

        $this->from('/timetable')->actingAs($d['admin'])
            ->post('/timetable/entries', $this->slot($d, ['stream_id' => $d['blue']->id, 'subject_id' => $d['eng']->id, 'teacher_user_id' => null]))
            ->assertSessionHasErrors('timetable_period_id');
    }

    public function test_a_room_cannot_host_two_classes_at_once(): void
    {
        $d = $this->makeSchool();
        setPermissionsTeamId($d['school']->id);
        $other = User::create(['school_id' => $d['school']->id, 'name' => 'Ms Neema', 'email' => 'neema-room@test.local', 'password' => 'password', 'status' => 'active']);
        $other->assignRole('Teacher');

        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d, ['room' => 'Lab 1']));

        $this->from('/timetable')->actingAs($d['admin'])
            ->post('/timetable/entries', $this->slot($d, ['school_class_id' => $d['class2']->id, 'teacher_user_id' => $other->id, 'room' => 'Lab 1']))
            ->assertSessionHasErrors('room');

        // A different room, or editing the same lesson, is fine.
        $this->actingAs($d['admin'])
            ->post('/timetable/entries', $this->slot($d, ['school_class_id' => $d['class2']->id, 'teacher_user_id' => $other->id, 'room' => 'Lab 2']))
            ->assertSessionHasNoErrors();
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d, ['room' => 'Lab 1']))->assertSessionHasNoErrors();
    }

    public function test_lessons_cannot_be_scheduled_in_a_break(): void
    {
        $d = $this->makeSchool();

        $this->from('/timetable')->actingAs($d['admin'])
            ->post('/timetable/entries', $this->slot($d, ['timetable_period_id' => $d['brk']->id]))
            ->assertSessionHasErrors('timetable_period_id');
    }

    public function test_a_stream_timetable_includes_shared_whole_class_lessons(): void
    {
        $d = $this->makeSchool();
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d, ['teacher_user_id' => null]));

        $this->actingAs($d['admin'])->get("/timetable?view=class&school_class_id={$d['class1']->id}&stream_id={$d['blue']->id}")
            ->assertOk()->assertSee('Mathematics');
    }

    public function test_school_and_teacher_views_render_the_lessons(): void
    {
        $d = $this->makeSchool();
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d));

        $this->actingAs($d['admin'])->get('/timetable?view=school&day=1')->assertOk()->assertSee('Mathematics')->assertSee('Form 1');
        $this->actingAs($d['admin'])->get("/timetable?view=teacher&teacher_id={$d['teacher']->id}")
            ->assertOk()->assertSee('Mr Juma')->assertSee('Mathematics');
    }

    public function test_lesson_can_be_removed(): void
    {
        $d = $this->makeSchool();
        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d));
        $entry = TimetableEntry::first();

        $this->actingAs($d['admin'])->delete("/timetable/entries/{$entry->id}")->assertRedirect();
        $this->assertSame(0, TimetableEntry::count());
    }

    public function test_periods_can_be_created_updated_and_deleted_unless_in_use(): void
    {
        $d = $this->makeSchool();

        $this->actingAs($d['admin'])->post('/timetable/periods', ['name' => 'Period 2', 'start_time' => '08:40', 'end_time' => '09:20'])->assertRedirect();
        $period = TimetablePeriod::where('name', 'Period 2')->first();

        $this->actingAs($d['admin'])->put("/timetable/periods/{$period->id}", ['name' => 'Lesson 2', 'start_time' => '08:40', 'end_time' => '09:30'])->assertRedirect();
        $this->assertSame('Lesson 2', $period->fresh()->name);

        $this->from('/timetable')->actingAs($d['admin'])->post('/timetable/periods', ['name' => 'Bad', 'start_time' => '10:00', 'end_time' => '09:00'])
            ->assertSessionHasErrors('end_time');

        $this->actingAs($d['admin'])->post('/timetable/entries', $this->slot($d));
        $this->actingAs($d['admin'])->delete("/timetable/periods/{$d['p1']->id}")->assertSessionHasErrors('period');

        $this->actingAs($d['admin'])->delete("/timetable/periods/{$period->id}")->assertRedirect();
        $this->assertDatabaseMissing('timetable_periods', ['id' => $period->id]);
    }

    public function test_teachers_can_view_but_not_edit_the_timetable(): void
    {
        $d = $this->makeSchool();

        $this->actingAs($d['teacher'])->get('/timetable')->assertOk();
        $this->actingAs($d['teacher'])->get('/timetable?view=teacher')->assertOk()->assertSee('Mr Juma');
        $this->actingAs($d['teacher'])->post('/timetable/entries', $this->slot($d))->assertForbidden();
        $this->actingAs($d['teacher'])->post('/timetable/periods', ['name' => 'X', 'start_time' => '08:00', 'end_time' => '09:00'])->assertForbidden();
    }

    public function test_a_school_cannot_use_another_schools_class_or_period(): void
    {
        $a = $this->makeSchool('School A');
        $b = $this->makeSchool('School B');

        $this->from('/timetable')->actingAs($a['admin'])
            ->post('/timetable/entries', $this->slot($b, ['teacher_user_id' => null]))
            ->assertSessionHasErrors(['school_class_id', 'timetable_period_id', 'subject_id']);

        $this->assertSame(0, TimetableEntry::withoutGlobalScopes()->count());
    }
}
