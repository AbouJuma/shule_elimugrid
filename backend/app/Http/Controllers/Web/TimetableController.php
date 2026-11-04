<?php

namespace App\Http\Controllers\Web;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\TimetableRequirement;
use App\Models\User;
use App\Services\TimetableGenerator;
use App\Services\TimetableService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimetableController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $this->authorize('timetable.view');
        $school = $tenant->assertBound();

        $view = in_array($request->string('view')->toString(), ['school', 'teacher', 'periods', 'generate'], true)
            ? $request->string('view')->toString()
            : 'class';

        $periods = TimetablePeriod::query()->orderBy('sort_order')->orderBy('start_time')->get();
        $classes = SchoolClass::query()->with(['streams' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])->orderBy('order')->orderBy('name')->get();
        $teachers = User::query()->role('Teacher')->orderBy('name')->get();

        // The school's chosen days, plus any day that already has lessons (so nothing becomes invisible).
        $usedDays = TimetableEntry::query()->distinct()->pluck('day_of_week')->all();
        $days = collect(TimetableEntry::DAYS)->only(array_unique([...$school->schoolDays(), ...$usedDays]))->sortKeys()->all();

        $data = [
            'view' => $view, 'periods' => $periods, 'classes' => $classes, 'teachers' => $teachers,
            'subjects' => Subject::query()->orderBy('name')->get(), 'days' => $days,
        ];

        return view('school-admin.timetable.index', $data + match ($view) {
            'school' => $this->schoolView($request, $classes, $days),
            'teacher' => $this->teacherView($request, $teachers),
            'periods' => ['schoolDays' => $school->schoolDays()],
            'generate' => $this->generateView($classes, $teachers),
            default => $this->classView($request, $classes),
        });
    }

    protected function classView(Request $request, $classes): array
    {
        $class = $classes->firstWhere('id', $request->integer('school_class_id')) ?? $classes->first();
        $streamId = $request->integer('stream_id') ?: null;

        if ($streamId && ! $class?->streams->contains('id', $streamId)) {
            $streamId = null;
        }

        $grid = [];
        if ($class) {
            TimetableEntry::query()
                ->with(['subject', 'teacher', 'stream'])
                ->where('school_class_id', $class->id)
                // A stream's timetable includes lessons the whole class shares.
                ->when($streamId, fn ($q) => $q->where(fn ($q2) => $q2->where('stream_id', $streamId)->orWhereNull('stream_id')),
                    fn ($q) => $q->whereNull('stream_id'))
                ->get()
                ->each(function ($entry) use (&$grid) {
                    $grid[$entry->timetable_period_id][$entry->day_of_week] = $entry;
                });
        }

        return ['class' => $class, 'streamId' => $streamId, 'grid' => $grid];
    }

    protected function schoolView(Request $request, $classes, array $days): array
    {
        $day = (int) $request->input('day', min(max((int) now()->dayOfWeekIso, 1), 5));
        $day = array_key_exists($day, $days) ? $day : 1;

        $columns = collect();
        foreach ($classes as $class) {
            if ($class->streams->isEmpty()) {
                $columns->push(['key' => $class->id.'-0', 'label' => $class->name, 'class_id' => $class->id, 'stream_id' => null]);
            } else {
                foreach ($class->streams as $stream) {
                    $columns->push(['key' => $class->id.'-'.$stream->id, 'label' => $class->name.' '.$stream->name, 'class_id' => $class->id, 'stream_id' => $stream->id]);
                }
            }
        }

        $grid = [];
        TimetableEntry::query()->with(['subject', 'teacher'])->where('day_of_week', $day)->get()->each(function ($entry) use (&$grid, $classes) {
            $class = $classes->firstWhere('id', $entry->school_class_id);

            // A whole-class lesson shows under each of the class's streams.
            $targets = $entry->stream_id
                ? [$entry->school_class_id.'-'.$entry->stream_id]
                : ($class && $class->streams->isNotEmpty()
                    ? $class->streams->map(fn ($s) => $class->id.'-'.$s->id)->all()
                    : [$entry->school_class_id.'-0']);

            foreach ($targets as $key) {
                $grid[$entry->timetable_period_id][$key] = $entry;
            }
        });

        return ['day' => $day, 'columns' => $columns, 'grid' => $grid];
    }

    protected function generateView($classes, $teachers): array
    {
        $existing = TimetableRequirement::query()->get()->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->keyBy('subject_id'));

        $subjectsByClass = [];
        foreach ($classes as $class) {
            $subjectsByClass[$class->id] = $class->subjects()->orderBy('name')->get();
        }

        return ['requirements' => $existing, 'subjectsByClass' => $subjectsByClass, 'lastReport' => session('timetable_report')];
    }

    protected function teacherView(Request $request, $teachers): array
    {
        $me = $teachers->firstWhere('id', $request->user()->id);
        $teacher = $teachers->firstWhere('id', $request->integer('teacher_id')) ?? $me ?? $teachers->first();

        $grid = [];
        $count = 0;
        if ($teacher) {
            TimetableEntry::query()->with(['subject', 'schoolClass', 'stream'])->where('teacher_user_id', $teacher->id)->get()
                ->each(function ($entry) use (&$grid, &$count) {
                    $grid[$entry->timetable_period_id][$entry->day_of_week] = $entry;
                    $count++;
                });
        }

        return ['teacher' => $teacher, 'grid' => $grid, 'lessonCount' => $count];
    }

    public function saveEntry(Request $request, TenantContext $tenant, TimetableService $timetable)
    {
        $this->authorize('timetable.manage');
        $school = $tenant->assertBound();

        $data = $request->validate([
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')->where('school_id', $school->id)],
            'stream_id' => ['nullable', Rule::exists('streams', 'id')->where('school_id', $school->id)->where('school_class_id', $request->input('school_class_id'))],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'timetable_period_id' => ['required', Rule::exists('timetable_periods', 'id')->where('school_id', $school->id)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('school_id', $school->id)],
            'teacher_user_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $school->id)],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        $timetable->setSlot($school, $data);

        return redirect()->route('school-admin.timetable.index', [
            'view' => 'class', 'school_class_id' => $data['school_class_id'], 'stream_id' => $data['stream_id'] ?? null,
        ])->with('status', __('Lesson saved.'));
    }

    public function updateDays(Request $request, TenantContext $tenant)
    {
        $this->authorize('timetable.manage');
        $school = $tenant->assertBound();

        $data = $request->validate([
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
        ]);

        $school->update(['settings' => [...($school->settings ?? []), 'school_days' => array_values(array_unique(array_map('intval', $data['days'])))]]);

        return redirect()->route('school-admin.timetable.index', ['view' => 'periods'])->with('status', __('School days updated.'));
    }

    public function saveRequirements(Request $request, TenantContext $tenant)
    {
        $this->authorize('timetable.manage');
        $school = $tenant->assertBound();

        $data = $request->validate([
            'req' => ['nullable', 'array'],
            'req.*' => ['array'],
            'req.*.*.periods' => ['nullable', 'integer', 'min:0', 'max:20'],
            'req.*.*.teacher' => ['nullable', Rule::exists('users', 'id')->where('school_id', $school->id)],
        ]);

        $classIds = SchoolClass::query()->pluck('id')->all();
        $subjectIds = Subject::query()->pluck('id')->all();

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $school, $classIds, $subjectIds) {
            foreach ($data['req'] ?? [] as $classId => $subjects) {
                foreach ($subjects as $subjectId => $row) {
                    if (! in_array((int) $classId, $classIds, true) || ! in_array((int) $subjectId, $subjectIds, true)) {
                        continue;
                    }

                    $periods = (int) ($row['periods'] ?? 0);
                    $keys = ['school_id' => $school->id, 'school_class_id' => (int) $classId, 'subject_id' => (int) $subjectId];

                    if ($periods === 0) {
                        TimetableRequirement::withoutGlobalScopes()->where($keys)->delete();

                        continue;
                    }

                    TimetableRequirement::withoutGlobalScopes()->updateOrCreate($keys, [
                        'periods_per_week' => $periods,
                        'teacher_user_id' => ! empty($row['teacher']) ? (int) $row['teacher'] : null,
                    ]);
                }
            }
        });

        return redirect()->route('school-admin.timetable.index', ['view' => 'generate'])->with('status', __('Lesson requirements saved.'));
    }

    public function generate(Request $request, TenantContext $tenant, TimetableGenerator $generator)
    {
        $this->authorize('timetable.manage');
        $school = $tenant->assertBound();

        $report = $generator->generate($school, $request->boolean('replace'));

        return redirect()->route('school-admin.timetable.index', ['view' => 'generate'])
            ->with('status', __(':placed of :wanted lessons placed.', ['placed' => $report['placed'], 'wanted' => $report['wanted']]))
            ->with('timetable_report', $report['unplaced']);
    }

    public function destroyEntry(TimetableEntry $entry)
    {
        $this->authorize('timetable.manage');

        $entry->delete();

        return redirect()->route('school-admin.timetable.index', [
            'view' => 'class', 'school_class_id' => $entry->school_class_id, 'stream_id' => $entry->stream_id,
        ])->with('status', __('Lesson removed.'));
    }

    public function storePeriod(Request $request, TenantContext $tenant)
    {
        $this->authorize('timetable.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_break' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        TimetablePeriod::create([
            'school_id' => $tenant->assertBound()->id,
            ...$data,
            'is_break' => (bool) ($data['is_break'] ?? false),
            'sort_order' => $data['sort_order'] ?? (TimetablePeriod::max('sort_order') + 1),
        ]);

        return redirect()->route('school-admin.timetable.index', ['view' => 'periods'])->with('status', __('Period added.'));
    }

    public function updatePeriod(Request $request, TimetablePeriod $period)
    {
        $this->authorize('timetable.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_break' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $period->update([...$data, 'is_break' => (bool) ($data['is_break'] ?? false), 'sort_order' => $data['sort_order'] ?? $period->sort_order]);

        return redirect()->route('school-admin.timetable.index', ['view' => 'periods'])->with('status', __('Period updated.'));
    }

    public function destroyPeriod(TimetablePeriod $period)
    {
        $this->authorize('timetable.manage');

        if ($period->entries()->exists()) {
            return back()->withErrors(['period' => __('This period still has lessons scheduled — remove them first.')]);
        }

        $period->delete();

        return redirect()->route('school-admin.timetable.index', ['view' => 'periods'])->with('status', __('Period deleted.'));
    }
}
