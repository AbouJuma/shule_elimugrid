<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Academics\StoreAcademicYearRequest;
use App\Http\Requests\Academics\StoreSchoolClassesRequest;
use App\Http\Requests\Academics\StoreStreamsRequest;
use App\Http\Requests\Academics\UpdateSchoolClassRequest;
use App\Http\Requests\Academics\UpdateStreamRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Support\Tenancy\TenantContext;
use App\Models\Student;
use App\Support\Pagination\PageSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicSetupController extends \App\Http\Controllers\Controller
{
    public function index()
    {
        $this->authorize('academic_years.view');

        return view('school-admin.academics.index', [
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->withCount('students')->with('streams')->orderBy('order')->orderBy('name')->get(),
            'streams' => Stream::query()->withCount('students')->with('schoolClass')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function showClass(Request $request, SchoolClass $schoolClass)
    {
        $this->authorize('classes.view');

        $schoolClass->load(['streams' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')]);

        $stats = Student::query()
            ->where('school_class_id', $schoolClass->id)
            ->selectRaw("stream_id, COUNT(*) as total, SUM(status = 'active') as active, SUM(gender = 'male') as male, SUM(gender = 'female') as female")
            ->groupBy('stream_id')
            ->get()
            ->keyBy(fn ($row) => $row->stream_id ?? 'none');

        $row = fn ($stat, $id, $name, $capacity) => [
            'id' => $id, 'name' => $name, 'capacity' => $capacity,
            'total' => (int) ($stat->total ?? 0), 'active' => (int) ($stat->active ?? 0),
            'male' => (int) ($stat->male ?? 0), 'female' => (int) ($stat->female ?? 0),
        ];

        $streamRows = $schoolClass->streams->map(fn ($stream) => $row($stats->get($stream->id), $stream->id, $stream->name, $stream->capacity));

        if ($stats->has('none')) {
            $streamRows->push($row($stats->get('none'), null, __('No stream'), null));
        }

        $totals = [
            'all' => (int) $stats->sum('total'), 'active' => (int) $stats->sum('active'),
            'male' => (int) $stats->sum('male'), 'female' => (int) $stats->sum('female'),
        ];

        $streamFilter = $request->string('stream_id')->toString();
        $filterLabel = match (true) {
            $streamFilter === 'none' => __('No stream'),
            ctype_digit($streamFilter) => $schoolClass->streams->firstWhere('id', (int) $streamFilter)?->name,
            default => null,
        };

        $students = Student::query()
            ->with('stream')
            ->where('school_class_id', $schoolClass->id)
            ->when($streamFilter === 'none', fn ($q) => $q->whereNull('stream_id'))
            ->when(ctype_digit($streamFilter), fn ($q) => $q->where('stream_id', (int) $streamFilter))
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.academics.class-show', [
            'class' => $schoolClass, 'streamRows' => $streamRows, 'totals' => $totals,
            'students' => $students, 'filterLabel' => $filterLabel,
        ]);
    }

    public function storeYear(StoreAcademicYearRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        if ($data['is_current'] ?? false) {
            AcademicYear::query()->update(['is_current' => false]);
        }

        AcademicYear::create(['school_id' => $tenant->id(), ...$data]);

        return redirect()->route('school-admin.academics.index', ['tab' => 'years'])->with('status', 'Academic year created.');
    }

    public function storeClasses(StoreSchoolClassesRequest $request, TenantContext $tenant)
    {
        $rows = $request->validated()['classes'];

        DB::transaction(function () use ($rows, $tenant) {
            foreach ($rows as $row) {
                SchoolClass::create([
                    'school_id' => $tenant->id(),
                    'name' => $row['name'],
                    'order' => $row['order'] ?? 0,
                ]);
            }
        });

        $count = count($rows);

        return redirect()->route('school-admin.academics.index', ['tab' => 'classes'])
            ->with('status', $count === 1 ? __('Class added.') : __(':n classes added.', ['n' => $count]));
    }

    public function updateClass(UpdateSchoolClassRequest $request, SchoolClass $schoolClass)
    {
        $schoolClass->update($request->validated());

        return redirect()->route('school-admin.academics.index', ['tab' => 'classes'])->with('status', 'Class updated.');
    }

    public function destroyClass(SchoolClass $schoolClass)
    {
        $this->authorize('classes.manage');

        if ($schoolClass->students()->exists()) {
            return back()->withErrors(['class' => 'This class still has students enrolled — move or archive them first.']);
        }

        $schoolClass->delete();

        return redirect()->route('school-admin.academics.index', ['tab' => 'classes'])->with('status', 'Class deleted.');
    }

    public function moveClass(SchoolClass $schoolClass, string $direction)
    {
        $this->authorize('classes.manage');

        $this->swapOrder(
            SchoolClass::query(),
            $schoolClass,
            'order',
            $direction
        );

        return redirect()->route('school-admin.academics.index', ['tab' => 'classes']);
    }

    public function storeStreams(StoreStreamsRequest $request, TenantContext $tenant)
    {
        $rows = $request->validated()['streams'];

        DB::transaction(function () use ($rows, $tenant) {
            foreach ($rows as $row) {
                Stream::create([
                    'school_id' => $tenant->id(),
                    'school_class_id' => $row['school_class_id'],
                    'name' => $row['name'],
                    'capacity' => $row['capacity'] ?? null,
                    'sort_order' => $row['sort_order'] ?? 0,
                ]);
            }
        });

        $count = count($rows);

        return redirect()->route('school-admin.academics.index', ['tab' => 'streams'])
            ->with('status', $count === 1 ? __('Stream added.') : __(':n streams added.', ['n' => $count]));
    }

    public function updateStream(UpdateStreamRequest $request, Stream $stream)
    {
        $stream->update($request->validated());

        return redirect()->route('school-admin.academics.index', ['tab' => 'streams'])->with('status', 'Stream updated.');
    }

    public function destroyStream(Stream $stream)
    {
        $this->authorize('classes.manage');

        if ($stream->students()->exists()) {
            return back()->withErrors(['stream' => 'This stream still has students enrolled — move or archive them first.']);
        }

        $stream->delete();

        return redirect()->route('school-admin.academics.index', ['tab' => 'streams'])->with('status', 'Stream deleted.');
    }

    public function moveStream(Stream $stream, string $direction)
    {
        $this->authorize('classes.manage');

        $this->swapOrder(
            Stream::query()->where('school_class_id', $stream->school_class_id),
            $stream,
            'sort_order',
            $direction
        );

        return redirect()->route('school-admin.academics.index', ['tab' => 'streams']);
    }

    /**
     * Swaps the sort column between $model and its immediate neighbour
     * (within $scope, already filtered to the right sibling set) — a
     * simple up/down reorder rather than a full drag-and-drop list, since
     * these lists are short (a handful of classes/streams per school).
     */
    protected function swapOrder($scope, $model, string $column, string $direction): void
    {
        $neighbour = $direction === 'up'
            ? (clone $scope)->where($column, '<', $model->{$column})->orderByDesc($column)->orderByDesc('id')->first()
            : (clone $scope)->where($column, '>', $model->{$column})->orderBy($column)->orderBy('id')->first();

        if (! $neighbour) {
            return;
        }

        DB::transaction(function () use ($model, $neighbour, $column) {
            $a = $model->{$column};
            $b = $neighbour->{$column};
            $model->update([$column => $b]);
            $neighbour->update([$column => $a]);
        });
    }
}
