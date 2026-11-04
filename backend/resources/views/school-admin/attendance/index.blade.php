@extends('layouts.portal')

@section('title', 'Attendance — ElimuGrid')
@section('page-title', __('Attendance Register'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'attendance'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small">{{ __('Class') }}</label>
                    <select name="school_class_id" class="form-select" required>
                        <option value="">{{ __('Select class…') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected($selectedClassId == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">{{ __('Date') }}</label>
                    <input type="date" name="date" class="form-control" value="{{ $date }}" max="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-gold w-100">{{ __('Load Register') }}</button>
                </div>
                {{-- Keeps the picked tab across a Class/Date reload. --}}
                <input type="hidden" name="subject_id" value="{{ $selectedSubjectId }}">
            </form>
        </div>
    </div>

    @if ($register)
        {{-- "By Class" is the original whole-day register (subject_id
             null); each tab after it is one subject the class offers,
             recorded as its own independent register for the same date. --}}
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link @if (!$selectedSubjectId) active @endif"
                   href="{{ route('school-admin.attendance.index', ['school_class_id' => $selectedClassId, 'date' => $date]) }}">
                    {{ __('By Class') }}
                </a>
            </li>
            @foreach ($subjects as $subject)
                <li class="nav-item">
                    <a class="nav-link @if ($selectedSubjectId == $subject->id) active @endif"
                       href="{{ route('school-admin.attendance.index', ['school_class_id' => $selectedClassId, 'date' => $date, 'subject_id' => $subject->id]) }}">
                        {{ $subject->name }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    {{ $register['class']->name }}
                    @if ($selectedSubjectId)
                        — {{ $subjects->firstWhere('id', $selectedSubjectId)?->name }}
                    @endif
                    — {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}
                </span>
                @if ($lock)
                    <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-3 py-2">
                        <i class="bi bi-lock-fill me-1"></i>
                        {{ __('Locked by :name', ['name' => $lock->lockedBy?->name ?? __('someone no longer with the school')]) }}
                        · {{ $lock->locked_at->format('d M, H:i') }}
                    </span>
                @elseif ($register['students']->isNotEmpty())
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="markAllPresent">{{ __('Mark all Present') }}</button>
                        <button type="button" class="btn btn-outline-secondary" id="markAllAbsent">{{ __('Mark all Absent') }}</button>
                    </div>
                @endif
            </div>

            @if ($register['students']->isEmpty())
                <div class="card-body text-center text-secondary py-5">
                    <i class="bi bi-people display-6 d-block mb-2"></i>
                    {{ __('No students in this class yet.') }}
                </div>
            @else
                <form method="POST" action="{{ route('school-admin.attendance.store') }}" id="attendanceForm">
                    @csrf
                    <input type="hidden" name="school_class_id" value="{{ $register['class']->id }}">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="subject_id" value="{{ $selectedSubjectId }}">

                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Admission No.') }}</th>
                                <th>{{ __('Student') }}</th>
                                <th style="width: 320px;">{{ __('Status') }}</th>
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($register['students'] as $i => $row)
                                @php($status = $row['status'] ?? 'present')
                                <tr>
                                    <td class="text-secondary small">{{ $row['student']->admission_number }}</td>
                                    <td>{{ $row['student']->fullName() }}</td>
                                    <td>
                                        <input type="hidden" name="records[{{ $i }}][student_id]" value="{{ $row['student']->id }}">
                                        <div class="btn-group btn-group-sm attendance-status-group" role="group">
                                            @foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
                                                <input type="radio" class="btn-check" autocomplete="off"
                                                       name="records[{{ $i }}][status]" id="status{{ $i }}_{{ $value }}"
                                                       value="{{ $value }}" @checked($status === $value) @disabled($lock)>
                                                <label class="btn btn-outline-secondary" for="status{{ $i }}_{{ $value }}">{{ $label }}</label>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="records[{{ $i }}][remarks]" class="form-control form-control-sm"
                                               value="{{ $row['remarks'] }}" placeholder="{{ __('Optional') }}" @disabled($lock)>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @unless ($lock)
                        <div class="card-footer bg-white">
                            <button type="submit" class="btn btn-gold">{{ __('Save Attendance') }}</button>
                        </div>
                    @endunless
                </form>

                @if ($lock)
                    <div class="card-footer bg-white">
                        @can('attendance.unlock')
                            <form method="POST" action="{{ route('school-admin.attendance.unlock') }}"
                                  onsubmit="return confirm('{{ __('Unlock this register so it can be edited again?') }}');">
                                @csrf
                                <input type="hidden" name="school_class_id" value="{{ $register['class']->id }}">
                                <input type="hidden" name="date" value="{{ $date }}">
                                <input type="hidden" name="subject_id" value="{{ $selectedSubjectId }}">
                                <button type="submit" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-unlock me-1"></i> {{ __('Unlock to Edit') }}
                                </button>
                            </form>
                        @else
                            <span class="text-secondary small">{{ __('This register is locked. Ask an administrator to unlock it to make changes.') }}</span>
                        @endcan
                    </div>
                @endif

                <script>
                    (function () {
                        function markAll(status) {
                            document.querySelectorAll('#attendanceForm input[type="radio"][value="' + status + '"]')
                                .forEach((radio) => { radio.checked = true; });
                        }
                        document.getElementById('markAllPresent')?.addEventListener('click', () => markAll('present'));
                        document.getElementById('markAllAbsent')?.addEventListener('click', () => markAll('absent'));
                    })();
                </script>
            @endif
        </div>
    @endif
@endsection
