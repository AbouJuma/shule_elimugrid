@extends('layouts.portal')

@section('title', __('Timetable').' — ElimuGrid')
@section('page-title', __('Timetable'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'timetable'])
@endsection

@php
    $canManage = auth()->user()->can('timetable.manage');
    $dayName = fn ($n) => __(\App\Models\TimetableEntry::DAYS[$n] ?? '');
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-success no-print">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger no-print"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <ul class="nav nav-tabs mb-3 no-print">
        <li class="nav-item"><a class="nav-link {{ $view === 'class' ? 'active' : '' }}" href="{{ route('school-admin.timetable.index', ['view' => 'class']) }}">{{ __('Class timetable') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $view === 'school' ? 'active' : '' }}" href="{{ route('school-admin.timetable.index', ['view' => 'school']) }}">{{ __('Whole school') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $view === 'teacher' ? 'active' : '' }}" href="{{ route('school-admin.timetable.index', ['view' => 'teacher']) }}">{{ __('By teacher') }}</a></li>
        @if ($canManage)
            <li class="nav-item"><a class="nav-link {{ $view === 'generate' ? 'active' : '' }}" href="{{ route('school-admin.timetable.index', ['view' => 'generate']) }}"><i class="bi bi-magic me-1"></i>{{ __('Auto-generate') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ $view === 'periods' ? 'active' : '' }}" href="{{ route('school-admin.timetable.index', ['view' => 'periods']) }}">{{ __('Periods') }}</a></li>
        @endif
    </ul>

    @if ($periods->isEmpty() && $view !== 'periods')
        <div class="alert alert-warning">
            {{ __('No periods defined yet.') }}
            @if ($canManage)<a href="{{ route('school-admin.timetable.index', ['view' => 'periods']) }}">{{ __('Set up the daily periods first.') }}</a>@endif
        </div>
    @endif

    {{-- ======================= CLASS VIEW ======================= --}}
    @if ($view === 'class')
        <div class="card mb-3 no-print">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="view" value="class">
                    <div class="col-md-4">
                        <label class="form-label small">{{ __('Class') }}</label>
                        <select name="school_class_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($classes as $c)
                                <option value="{{ $c->id }}" @selected($class?->id === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">{{ __('Stream') }}</label>
                        <select name="stream_id" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ __('Whole class') }}</option>
                            @foreach ($class?->streams ?? [] as $s)
                                <option value="{{ $s->id }}" @selected($streamId === $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> {{ __('Print') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($class && $periods->isNotEmpty())
            <div class="card">
                <div class="card-header bg-white">
                    {{ $class->name }}@if ($streamId) — {{ $class->streams->firstWhere('id', $streamId)?->name }}@endif
                    @if ($streamId)<span class="text-secondary small ms-2">({{ __('includes lessons shared by the whole class') }})</span>@endif
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle text-center timetable-grid">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 130px;">{{ __('Period') }}</th>
                                @foreach ($days as $n => $label)<th>{{ __($label) }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($periods as $period)
                                <tr class="{{ $period->is_break ? 'table-secondary' : '' }}">
                                    <th class="text-start small">
                                        {{ $period->name }}<div class="text-secondary fw-normal">{{ $period->timeLabel() }}</div>
                                    </th>
                                    @if ($period->is_break)
                                        <td colspan="{{ count($days) }}" class="text-secondary small">{{ $period->name }}</td>
                                    @else
                                        @foreach ($days as $n => $label)
                                            @php $entry = $grid[$period->id][$n] ?? null; @endphp
                                            <td class="p-1">
                                                @if ($entry)
                                                    <div class="tt-cell text-start {{ $canManage ? 'tt-editable' : '' }}"
                                                         @if ($canManage)
                                                         data-bs-toggle="modal" data-bs-target="#slotModal" role="button"
                                                         data-day="{{ $n }}" data-period="{{ $period->id }}" data-entry="{{ $entry->id }}"
                                                         data-subject="{{ $entry->subject_id }}" data-teacher="{{ $entry->teacher_user_id }}" data-room="{{ $entry->room }}"
                                                         data-title="{{ $dayName($n) }} · {{ $period->name }}"
                                                         @endif>
                                                        <div class="fw-semibold small">{{ $entry->subject->name }}</div>
                                                        <div class="text-secondary" style="font-size: .75rem;">{{ $entry->teacher?->name ?? '—' }}@if ($entry->room) · {{ $entry->room }}@endif</div>
                                                    </div>
                                                @elseif ($canManage)
                                                    <button type="button" class="btn btn-sm btn-link text-secondary w-100 no-print"
                                                            data-bs-toggle="modal" data-bs-target="#slotModal"
                                                            data-day="{{ $n }}" data-period="{{ $period->id }}" data-entry=""
                                                            data-subject="" data-teacher="" data-room="" data-title="{{ $dayName($n) }} · {{ $period->name }}">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($canManage && $class)
            <div class="modal fade" id="slotModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.timetable.entries.save') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $class->id }}">
                            <input type="hidden" name="stream_id" value="{{ $streamId }}">
                            <input type="hidden" name="day_of_week" id="slotDay">
                            <input type="hidden" name="timetable_period_id" id="slotPeriod">
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="slotTitle">{{ __('Lesson') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Subject') }}</label>
                                    <select name="subject_id" id="slotSubject" class="form-select" required>
                                        <option value="">{{ __('Select subject…') }}</option>
                                        @foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Teacher') }} <span class="text-secondary">({{ __('optional') }})</span></label>
                                    <select name="teacher_user_id" id="slotTeacher" class="form-select">
                                        <option value="">{{ __('Not assigned') }}</option>
                                        @foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="mb-1">
                                    <label class="form-label">{{ __('Room') }} <span class="text-secondary">({{ __('optional') }})</span></label>
                                    <input type="text" name="room" id="slotRoom" class="form-control" maxlength="50">
                                </div>
                            </div>
                            <div class="modal-footer justify-content-between">
                                <button type="submit" form="slotDeleteForm" class="btn btn-outline-danger" id="slotDeleteBtn">{{ __('Remove lesson') }}</button>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                                </div>
                            </div>
                    </form>
                </div>
                {{-- Not inside .modal-content: a second <form> there would stop it being modal-content's
                     sole flex child, breaking the scrollable layout (see the fix applied portal-wide for
                     the same reason). The delete button elsewhere submits this one via its `form` attribute. --}}
                <form method="POST" id="slotDeleteForm" action="#">@csrf @method('DELETE')</form>
            </div>
            <script>
                document.getElementById('slotModal')?.addEventListener('show.bs.modal', (event) => {
                    const d = event.relatedTarget.dataset;
                    const set = (id, value) => {
                        const el = document.getElementById(id);
                        if (el.tomselect) el.tomselect.setValue(value); else el.value = value;
                    };
                    document.getElementById('slotTitle').textContent = d.title;
                    document.getElementById('slotDay').value = d.day;
                    document.getElementById('slotPeriod').value = d.period;
                    set('slotSubject', d.subject || '');
                    set('slotTeacher', d.teacher || '');
                    document.getElementById('slotRoom').value = d.room || '';
                    const del = document.getElementById('slotDeleteBtn');
                    del.style.display = d.entry ? '' : 'none';
                    document.getElementById('slotDeleteForm').action = d.entry ? '{{ url('/timetable/entries') }}/' + d.entry : '#';
                });
            </script>
        @endif
    @endif

    {{-- ======================= SCHOOL VIEW ======================= --}}
    @if ($view === 'school')
        <div class="card mb-3 no-print">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="btn-group">
                    @foreach ($days as $n => $label)
                        <a href="{{ route('school-admin.timetable.index', ['view' => 'school', 'day' => $n]) }}" class="btn btn-sm {{ $day === $n ? 'btn-gold' : 'btn-outline-secondary' }}">{{ __($label) }}</a>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> {{ __('Print') }}</button>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white">{{ $dayName($day) }} — {{ __('all classes') }}</div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0 align-middle text-center timetable-grid">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 130px;">{{ __('Period') }}</th>
                            @foreach ($columns as $col)<th class="small">{{ $col['label'] }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($periods as $period)
                            <tr class="{{ $period->is_break ? 'table-secondary' : '' }}">
                                <th class="text-start small">{{ $period->name }}<div class="text-secondary fw-normal">{{ $period->timeLabel() }}</div></th>
                                @if ($period->is_break)
                                    <td colspan="{{ max(1, $columns->count()) }}" class="text-secondary small">{{ $period->name }}</td>
                                @else
                                    @foreach ($columns as $col)
                                        @php $entry = $grid[$period->id][$col['key']] ?? null; @endphp
                                        <td class="p-1 text-start">
                                            @if ($entry)
                                                <div class="fw-semibold small">{{ $entry->subject->name }}</div>
                                                <div class="text-secondary" style="font-size: .75rem;">{{ $entry->teacher?->name ?? '—' }}</div>
                                            @endif
                                        </td>
                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ======================= TEACHER VIEW ======================= --}}
    @if ($view === 'teacher')
        <div class="card mb-3 no-print">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="view" value="teacher">
                    <div class="col-md-6">
                        <label class="form-label small">{{ __('Teacher') }}</label>
                        <select name="teacher_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($teacher?->id === $t->id)>{{ $t->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> {{ __('Print') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @if ($teacher)
            <div class="card">
                <div class="card-header bg-white">{{ $teacher->name }} — {{ trans_choice(':count lesson a week|:count lessons a week', $lessonCount, ['count' => $lessonCount]) }}</div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle text-center timetable-grid">
                        <thead class="table-light">
                            <tr><th style="width: 130px;">{{ __('Period') }}</th>@foreach ($days as $n => $label)<th>{{ __($label) }}</th>@endforeach</tr>
                        </thead>
                        <tbody>
                            @foreach ($periods as $period)
                                <tr class="{{ $period->is_break ? 'table-secondary' : '' }}">
                                    <th class="text-start small">{{ $period->name }}<div class="text-secondary fw-normal">{{ $period->timeLabel() }}</div></th>
                                    @if ($period->is_break)
                                        <td colspan="{{ count($days) }}" class="text-secondary small">{{ $period->name }}</td>
                                    @else
                                        @foreach ($days as $n => $label)
                                            @php $entry = $grid[$period->id][$n] ?? null; @endphp
                                            <td class="p-1 text-start">
                                                @if ($entry)
                                                    <div class="fw-semibold small">{{ $entry->subject->name }}</div>
                                                    <div class="text-secondary" style="font-size: .75rem;">{{ $entry->schoolClass->name }}@if ($entry->stream) {{ $entry->stream->name }}@endif @if ($entry->room)· {{ $entry->room }}@endif</div>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="alert alert-info">{{ __('No teachers found. Give staff the Teacher role first.') }}</div>
        @endif
    @endif

    {{-- ======================= PERIODS ======================= --}}
    @if ($view === 'generate' && $canManage)
        <div class="alert alert-light border small">
            {{ __('Tell the system what each class studies — the teacher and how many lessons a week — then generate. It fills only empty slots, never moves a lesson that is already there, and never puts a teacher in two classes at once. Anything it cannot fit is listed below instead of being forced in.') }}
        </div>

        @if ($lastReport)
            <div class="card border-warning mb-3">
                <div class="card-header bg-warning-subtle">{{ __('Could not place everything') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($lastReport as $row)
                        <li class="list-group-item">
                            <strong>{{ $row['class'] }} — {{ $row['subject'] }}</strong>: {{ __(':n lesson(s) missing', ['n' => $row['missing']]) }} —
                            {{ ['teacher_busy' => __('the teacher is busy in every free slot of this class'), 'class_full' => __('the class timetable is already full'), 'daily_cap' => __('no free day left without stacking the subject'), 'no_slot' => __('no free slot')][$row['reason']] ?? $row['reason'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('school-admin.timetable.requirements.save') }}">
            @csrf @method('PUT')
            @foreach ($classes as $class)
                @php $subs = $subjectsByClass[$class->id] ?? collect(); $reqs = $requirements[$class->id] ?? collect(); @endphp
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span class="fw-semibold">{{ $class->name }}</span>
                        <span class="text-secondary small">{{ __(':n lessons a week in total', ['n' => $reqs->sum('periods_per_week')]) }}</span>
                    </div>
                    @if ($subs->isEmpty())
                        <div class="card-body text-secondary small">{{ __('No subjects are linked to this class yet — link them under Academics → Subjects.') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
                                <thead class="table-light"><tr><th>{{ __('Subject') }}</th><th style="width: 260px;">{{ __('Teacher') }}</th><th style="width: 140px;">{{ __('Lessons / week') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($subs as $subject)
                                        @php $r = $reqs[$subject->id] ?? null; @endphp
                                        <tr>
                                            <td>{{ $subject->name }}</td>
                                            <td>
                                                <select name="req[{{ $class->id }}][{{ $subject->id }}][teacher]" class="form-select form-select-sm">
                                                    <option value="">{{ __('— Any / not set —') }}</option>
                                                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($r?->teacher_user_id === $t->id)>{{ $t->name }}</option>@endforeach
                                                </select>
                                            </td>
                                            <td><input type="number" min="0" max="20" name="req[{{ $class->id }}][{{ $subject->id }}][periods]" class="form-control form-control-sm" value="{{ $r?->periods_per_week ?? 0 }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
            @if ($classes->isNotEmpty())
                <button class="btn btn-gold"><i class="bi bi-save me-1"></i> {{ __('Save requirements') }}</button>
            @endif
        </form>

        <form method="POST" action="{{ route('school-admin.timetable.generate') }}" class="card mt-3" onsubmit="return confirm('{{ __('Generate the timetable now?') }}');">
            @csrf
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="replace" value="1" id="replaceGen">
                    <label class="form-check-label" for="replaceGen">{{ __('First remove lessons created by an earlier generation (lessons you placed by hand are kept)') }}</label>
                </div>
                <button class="btn btn-gold ms-auto"><i class="bi bi-magic me-1"></i> {{ __('Generate timetable') }}</button>
            </div>
        </form>
    @endif
    @if ($view === 'periods' && $canManage)
        <div class="card mb-3">
            <div class="card-header bg-white">{{ __('School days') }}</div>
            <form method="POST" action="{{ route('school-admin.timetable.days.update') }}" class="card-body d-flex flex-wrap align-items-center gap-3">
                @csrf @method('PUT')
                @foreach (\App\Models\TimetableEntry::DAYS as $n => $label)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="days[]" value="{{ $n }}" id="day{{ $n }}" @checked(in_array($n, $schoolDays))>
                        <label class="form-check-label" for="day{{ $n }}">{{ __($label) }}</label>
                    </div>
                @endforeach
                <button class="btn btn-gold btn-sm ms-auto">{{ __('Save days') }}</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>{{ __('Daily periods') }}</span>
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newPeriodModal"><i class="bi bi-plus-lg"></i> {{ __('Add Period') }}</button>
            </div>
            @if ($periods->isEmpty())
                <div class="card-body text-center text-secondary py-5">{{ __('No periods yet. Add your school day: e.g. Period 1 08:00–08:40, Break 10:00–10:20…') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>#</th><th>{{ __('Name') }}</th><th>{{ __('Time') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($periods as $period)
                                <tr>
                                    <td class="text-secondary">{{ $period->sort_order }}</td>
                                    <td class="fw-semibold">{{ $period->name }}</td>
                                    <td>{{ $period->timeLabel() }}</td>
                                    <td>{!! $period->is_break ? '<span class="badge bg-secondary-subtle text-secondary">'.e(__('Break')).'</span>' : e(__('Lesson')) !!}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editPeriodModal{{ $period->id }}"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="{{ route('school-admin.timetable.periods.destroy', $period) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this period?') }}');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @foreach ([['id' => 'new', 'period' => null], ...$periods->map(fn ($p) => ['id' => $p->id, 'period' => $p])->all()] as $m)
            @php $p = $m['period']; @endphp
            <div class="modal fade" id="{{ $p ? 'editPeriodModal'.$p->id : 'newPeriodModal' }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ $p ? route('school-admin.timetable.periods.update', $p) : route('school-admin.timetable.periods.store') }}">
                            @csrf @if ($p) @method('PUT') @endif
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ $p ? __('Edit Period') : __('Add Period') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3"><label class="form-label">{{ __('Name') }}</label><input type="text" name="name" class="form-control" value="{{ $p?->name }}" placeholder="{{ __('Period 1') }}" required></div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6"><label class="form-label">{{ __('Starts') }}</label><input type="time" name="start_time" class="form-control" value="{{ $p ? substr($p->start_time, 0, 5) : '' }}" required></div>
                                    <div class="col-6"><label class="form-label">{{ __('Ends') }}</label><input type="time" name="end_time" class="form-control" value="{{ $p ? substr($p->end_time, 0, 5) : '' }}" required></div>
                                </div>
                                <div class="row g-3 align-items-end">
                                    <div class="col-6"><label class="form-label">{{ __('Order') }}</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ $p?->sort_order }}"></div>
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_break" value="1" id="brk{{ $m['id'] }}" @checked($p?->is_break)>
                                            <label class="form-check-label" for="brk{{ $m['id'] }}">{{ __('This is a break') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ $p ? __('Save Changes') : __('Add Period') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach
    @endif
@endsection
