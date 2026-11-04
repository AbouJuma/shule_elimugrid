<?php

namespace Tests\Feature\Timetable;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\TimetableRequirement;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\TimetableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TimetableGeneratorTest extends TestCase
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

    protected function periods(int $lessons, bool $withBreak = true): array
    {
        $ids = [];

        for ($i = 1; $i <= $lessons; $i++) {
            $ids[] = TimetablePeriod::create(['school_id' => $this->school->id, 'name' => "P$i", 'start_time' => sprintf('%02d:00', 7 + $i), 'end_time' => sprintf('%02d:40', 7 + $i), 'sort_order' => $i])->id;
        }

        if ($withBreak) {
            TimetablePeriod::create(['school_id' => $this->school->id, 'name' => 'Break', 'start_time' => '10:00', 'end_time' => '10:20', 'is_break' => true, 'sort_order' => 99]);
        }

        return $ids;
    }

    protected function klass(string $name): SchoolClass
    {
        return SchoolClass::create(['school_id' => $this->school->id, 'name' => $name]);
    }

    protected function subject(string $name): Subject
    {
        return Subject::create(['school_id' => $this->school->id, 'name' => $name]);
    }

    protected function teacher(string $name): User
    {
        setPermissionsTeamId($this->school->id);
        $user = User::create(['school_id' => $this->school->id, 'name' => $name, 'email' => str($name)->slug().'@test.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole('Teacher');

        return $user;
    }

    protected function need(SchoolClass $class, Subject $subject, ?User $teacher, int $perWeek): void
    {
        TimetableRequirement::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_user_id' => $teacher?->id, 'periods_per_week' => $perWeek]);
    }

    public function test_it_places_the_requested_lessons_without_any_clash_or_break_lesson(): void
    {
        $this->periods(4);
        [$f1, $f2] = [$this->klass('Form 1'), $this->klass('Form 2')];
        [$math, $eng, $sci] = [$this->subject('Maths'), $this->subject('English'), $this->subject('Science')];
        [$juma, $neema] = [$this->teacher('Juma'), $this->teacher('Neema')];

        // Juma teaches Maths to both forms and Neema teaches English to both — every slot Juma takes must be free of Neema's clash and his own.
        foreach ([$f1, $f2] as $class) {
            $this->need($class, $math, $juma, 5);
            $this->need($class, $eng, $neema, 4);
            $this->need($class, $sci, null, 3);
        }

        $report = app(TimetableGenerator::class)->generate($this->school);

        $this->assertSame(24, $report['wanted']);
        $this->assertSame(24, $report['placed']);
        $this->assertSame([], $report['unplaced']);

        $entries = TimetableEntry::all();
        $this->assertCount(24, $entries);
        $this->assertTrue($entries->every(fn ($e) => $e->generated));

        // No teacher in two places at once, no class double-booked.
        $this->assertSame(0, $entries->whereNotNull('teacher_user_id')->groupBy(fn ($e) => "$e->teacher_user_id-$e->day_of_week-$e->timetable_period_id")->filter(fn ($g) => $g->count() > 1)->count());
        $this->assertSame(0, $entries->groupBy(fn ($e) => "$e->school_class_id-$e->day_of_week-$e->timetable_period_id")->filter(fn ($g) => $g->count() > 1)->count());

        // Never in the break, never on a day the school doesn't run (default Mon–Fri).
        $breakId = TimetablePeriod::where('is_break', true)->value('id');
        $this->assertSame(0, $entries->where('timetable_period_id', $breakId)->count());
        $this->assertSame([], $entries->pluck('day_of_week')->unique()->diff([1, 2, 3, 4, 5])->values()->all());

        // Each subject lands exactly as many times as asked.
        $this->assertSame(5, $entries->where('school_class_id', $f1->id)->where('subject_id', $math->id)->count());
        $this->assertSame(4, $entries->where('school_class_id', $f2->id)->where('subject_id', $eng->id)->count());
    }

    public function test_a_subject_is_spread_across_the_week_not_stacked_on_one_day(): void
    {
        $this->periods(6);
        $class = $this->klass('Form 1');
        $math = $this->subject('Maths');
        $this->need($class, $math, null, 5);

        app(TimetableGenerator::class)->generate($this->school);

        $days = TimetableEntry::pluck('day_of_week')->all();
        $this->assertCount(5, array_unique($days), 'five lessons should use five different days');
    }

    public function test_lessons_already_in_the_grid_are_kept_and_count_towards_the_target(): void
    {
        [$p1] = $this->periods(3);
        $class = $this->klass('Form 1');
        $math = $this->subject('Maths');
        $juma = $this->teacher('Juma');
        $this->need($class, $math, $juma, 3);

        $manual = TimetableEntry::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'day_of_week' => 1, 'timetable_period_id' => $p1, 'subject_id' => $math->id, 'teacher_user_id' => $juma->id]);

        $report = app(TimetableGenerator::class)->generate($this->school);

        $this->assertSame(2, $report['wanted'], 'one of the three is already placed');
        $this->assertSame(3, TimetableEntry::where('subject_id', $math->id)->count());
        $this->assertFalse($manual->fresh()->generated);
        $this->assertSame(1, TimetableEntry::where('day_of_week', 1)->where('timetable_period_id', $p1)->count(), 'the hand-placed slot is not double-booked');
    }

    public function test_a_teacher_who_cannot_be_in_two_places_leaves_the_excess_unplaced_with_a_reason(): void
    {
        $this->periods(1);              // 1 lesson a day × 5 days = 5 slots per class
        [$f1, $f2] = [$this->klass('Form 1'), $this->klass('Form 2')];
        $math = $this->subject('Maths');
        $juma = $this->teacher('Juma');

        // Juma is wanted 5 + 5 times but only has 5 slots in the whole week.
        $this->need($f1, $math, $juma, 5);
        $this->need($f2, $math, $juma, 5);

        $report = app(TimetableGenerator::class)->generate($this->school);

        $this->assertSame(10, $report['wanted']);
        $this->assertSame(5, $report['placed']);
        // Round-robin shares Juma's five slots fairly between the two forms, so both are short — by 5 lessons in total.
        $this->assertCount(2, $report['unplaced']);
        $this->assertSame(5, array_sum(array_column($report['unplaced'], 'missing')));
        $this->assertSame(['teacher_busy'], array_values(array_unique(array_column($report['unplaced'], 'reason'))));
        $this->assertSame(5, TimetableEntry::where('teacher_user_id', $juma->id)->count());
    }

    public function test_replace_only_removes_generated_lessons(): void
    {
        [$p1] = $this->periods(3);
        $class = $this->klass('Form 1');
        $math = $this->subject('Maths');
        $eng = $this->subject('English');
        $this->need($class, $eng, null, 2);

        $manual = TimetableEntry::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'day_of_week' => 2, 'timetable_period_id' => $p1, 'subject_id' => $math->id]);

        $generator = app(TimetableGenerator::class);
        $generator->generate($this->school);
        $this->assertSame(2, TimetableEntry::where('generated', true)->count());

        $generator->generate($this->school, replaceGenerated: true);

        $this->assertSame(2, TimetableEntry::where('generated', true)->count(), 'regenerated, not doubled');
        $this->assertNotNull(TimetableEntry::find($manual->id), 'the hand-placed lesson survives');
    }

    public function test_school_days_can_include_saturday_and_sunday(): void
    {
        $this->periods(6);
        $this->school->update(['settings' => ['school_days' => [1, 2, 3, 4, 5, 6, 7]]]);
        $class = $this->klass('Form 1');
        $math = $this->subject('Maths');
        $this->need($class, $math, null, 7);

        app(TimetableGenerator::class)->generate($this->school->fresh());

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], TimetableEntry::orderBy('day_of_week')->pluck('day_of_week')->all());
    }

    public function test_manager_can_choose_school_days_and_schedule_a_sunday_lesson_by_hand(): void
    {
        [$p1] = $this->periods(2);
        $class = $this->klass('Form 1');
        $math = $this->subject('Maths');
        setPermissionsTeamId($this->school->id);
        $admin = User::create(['school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'a@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        $this->actingAs($admin)->put(route('school-admin.timetable.days.update'), ['days' => [1, 2, 3, 4, 5, 7]])->assertSessionHasNoErrors();
        $this->assertSame([1, 2, 3, 4, 5, 7], $this->school->fresh()->schoolDays());

        $this->actingAs($admin)->post(route('school-admin.timetable.entries.save'), [
            'school_class_id' => $class->id, 'day_of_week' => 7, 'timetable_period_id' => $p1, 'subject_id' => $math->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, TimetableEntry::where('day_of_week', 7)->count());

        $this->actingAs($admin)->get(route('school-admin.timetable.index', ['view' => 'class', 'school_class_id' => $class->id]))
            ->assertOk()->assertSee('Sunday');

        $this->actingAs($admin)->put(route('school-admin.timetable.days.update'), ['days' => []])->assertSessionHasErrors('days');
    }

    public function test_manager_saves_requirements_and_generates_through_the_portal(): void
    {
        $this->periods(4);
        $class = $this->klass('Form 1');
        $math = $this->subject('Maths');
        $class->subjects()->attach($math->id);
        $juma = $this->teacher('Juma');
        setPermissionsTeamId($this->school->id);
        $admin = User::create(['school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'a@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        $this->actingAs($admin)->get(route('school-admin.timetable.index', ['view' => 'generate']))->assertOk()->assertSee('Maths');

        $this->actingAs($admin)->put(route('school-admin.timetable.requirements.save'), [
            'req' => [$class->id => [$math->id => ['teacher' => $juma->id, 'periods' => 3]]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, TimetableRequirement::first()->periods_per_week);

        $this->actingAs($admin)->post(route('school-admin.timetable.generate'))->assertSessionHas('status');
        $this->assertSame(3, TimetableEntry::count());

        // Zero lessons removes the requirement.
        $this->actingAs($admin)->put(route('school-admin.timetable.requirements.save'), [
            'req' => [$class->id => [$math->id => ['teacher' => '', 'periods' => 0]]],
        ]);
        $this->assertSame(0, TimetableRequirement::count());
    }

    public function test_teachers_cannot_generate_a_timetable(): void
    {
        $teacher = $this->teacher('Juma');

        $this->actingAs($teacher)->post(route('school-admin.timetable.generate'))->assertForbidden();
    }
}
