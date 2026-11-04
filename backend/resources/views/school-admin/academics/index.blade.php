@extends('layouts.portal')

@section('title', 'Academics — ElimuGrid')
@section('page-title', __('Academic Setup'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'academics'])
@endsection

@section('content')
    @php
        $activeTab = request('tab', 'years');
    @endphp

    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'years' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#paneYears" type="button">
                {{ __('Academic Years') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'classes' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#paneClasses" type="button">
                {{ __('Classes') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'streams' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#paneStreams" type="button">
                {{ __('Streams') }}
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ============ Academic Years ============ --}}
        <div class="tab-pane fade {{ $activeTab === 'years' ? 'show active' : '' }}" id="paneYears">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    Academic Years
                    @can('academic_years.manage')
                        <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newYearModal">
                            <i class="bi bi-plus-lg"></i> {{ __('Add') }}
                        </button>
                    @endcan
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($years as $year)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                {{ $year->name }}
                                <span class="text-secondary small">({{ $year->start_date->format('d M Y') }} – {{ $year->end_date->format('d M Y') }})</span>
                            </span>
                            @if ($year->is_current)
                                <span class="badge badge-status-active rounded-pill">{{ __('Current') }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-secondary small">{{ __('No academic years yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- ============ Classes ============ --}}
        <div class="tab-pane fade {{ $activeTab === 'classes' ? 'show active' : '' }}" id="paneClasses">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    Classes
                    @can('classes.manage')
                        <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newClassesModal">
                            <i class="bi bi-plus-lg"></i> {{ __('Add Classes') }}
                        </button>
                    @endcan
                </div>
                @if ($classes->isEmpty())
                    <div class="card-body text-center text-secondary py-5">{{ __('No classes yet.') }}</div>
                @else
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 1%;">{{ __('Order') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Streams') }}</th>
                                <th>{{ __('Students') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($classes as $class)
                                <tr>
                                    <td class="text-secondary">
                                        {{ $class->order }}
                                        @can('classes.manage')
                                            <div class="btn-group btn-group-sm ms-1">
                                                <form method="POST" action="{{ route('school-admin.academics.classes.move', [$class, 'up']) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1"><i class="bi bi-arrow-up"></i></button>
                                                </form>
                                                <form method="POST" action="{{ route('school-admin.academics.classes.move', [$class, 'down']) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1"><i class="bi bi-arrow-down"></i></button>
                                                </form>
                                            </div>
                                        @endcan
                                    </td>
                                    <td class="fw-semibold"><a href="{{ route('school-admin.academics.classes.show', $class) }}" class="text-decoration-none">{{ $class->name }}</a></td>
                                    <td class="text-secondary small">{{ $class->streams->pluck('name')->join(', ') ?: '—' }}</td>
                                    <td class="text-secondary small">{{ $class->students_count }}</td>
                                    <td class="text-end">
                                        @can('classes.manage')
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editClassModal{{ $class->id }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('school-admin.academics.classes.destroy', $class) }}" class="d-inline"
                                                  onsubmit="return confirm('Delete class \'{{ $class->name }}\'?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- ============ Streams ============ --}}
        <div class="tab-pane fade {{ $activeTab === 'streams' ? 'show active' : '' }}" id="paneStreams">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    Streams
                    @can('classes.manage')
                        <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newStreamsModal">
                            <i class="bi bi-plus-lg"></i> {{ __('Add Streams') }}
                        </button>
                    @endcan
                </div>
                @if ($streams->isEmpty())
                    <div class="card-body text-center text-secondary py-5">{{ __('No streams yet.') }}</div>
                @else
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 1%;">{{ __('Order') }}</th>
                                <th>{{ __('Class') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Capacity') }}</th>
                                <th>{{ __('Students') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($streams as $stream)
                                <tr>
                                    <td class="text-secondary">
                                        {{ $stream->sort_order }}
                                        @can('classes.manage')
                                            <div class="btn-group btn-group-sm ms-1">
                                                <form method="POST" action="{{ route('school-admin.academics.streams.move', [$stream, 'up']) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1"><i class="bi bi-arrow-up"></i></button>
                                                </form>
                                                <form method="POST" action="{{ route('school-admin.academics.streams.move', [$stream, 'down']) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1"><i class="bi bi-arrow-down"></i></button>
                                                </form>
                                            </div>
                                        @endcan
                                    </td>
                                    <td class="text-secondary small">{{ $stream->schoolClass->name }}</td>
                                    <td class="fw-semibold">{{ $stream->name }}</td>
                                    <td class="text-secondary small">{{ $stream->capacity ?? '—' }}</td>
                                    <td class="text-secondary small">{{ $stream->students_count }}</td>
                                    <td class="text-end">
                                        @can('classes.manage')
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editStreamModal{{ $stream->id }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('school-admin.academics.streams.destroy', $stream) }}" class="d-inline"
                                                  onsubmit="return confirm('Delete stream \'{{ $stream->name }}\'?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ============ Modals ============ --}}
    @can('academic_years.manage')
        <div class="modal fade" id="newYearModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.academics.years.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Add Academic Year') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="2026/2027" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Start date') }}</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('End date') }}</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_current" value="1" id="isCurrent">
                                <label class="form-check-label small" for="isCurrent">{{ __('Set as current year') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add') }}</button>
                        </div>
                    </form>
            </div>
        </div>
    @endcan

    @can('classes.manage')
        {{-- Add Classes — repeatable rows, one submission creates them all --}}
        <div class="modal fade" id="newClassesModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('school-admin.academics.classes.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Add Classes') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="classRows">
                                <div class="row g-2 mb-2 class-row">
                                    <div class="col-8">
                                        <input type="text" name="classes[0][name]" class="form-control" placeholder="{{ __('Form 1') }}" required>
                                    </div>
                                    <div class="col-3">
                                        <input type="number" name="classes[0][order]" class="form-control" placeholder="{{ __('Order') }}" min="0">
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-row" title="{{ __('Remove row') }}"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="addClassRow">
                                <i class="bi bi-plus-lg"></i> {{ __('Add another row') }}
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Save Classes') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        {{-- Add Streams — repeatable rows --}}
        <div class="modal fade" id="newStreamsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('school-admin.academics.streams.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Add Streams') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="streamRows">
                                <div class="row g-2 mb-2 stream-row">
                                    <div class="col-4">
                                        <select name="streams[0][school_class_id]" class="form-select" required>
                                            <option value="">{{ __('Class…') }}</option>
                                            @foreach ($classes as $class)
                                                <option value="{{ $class->id }}">{{ $class->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <input type="text" name="streams[0][name]" class="form-control" placeholder="{{ __('Blue') }}" required>
                                    </div>
                                    <div class="col-2">
                                        <input type="number" name="streams[0][capacity]" class="form-control" placeholder="{{ __('Capacity') }}" min="1">
                                    </div>
                                    <div class="col-1">
                                        <input type="number" name="streams[0][sort_order]" class="form-control" placeholder="{{ __('Order') }}" min="0">
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-row" title="{{ __('Remove row') }}"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="addStreamRow">
                                <i class="bi bi-plus-lg"></i> {{ __('Add another row') }}
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Save Streams') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        {{-- Edit Class modals --}}
        @foreach ($classes as $class)
            <div class="modal fade" id="editClassModal{{ $class->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.academics.classes.update', $class) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Class') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $class->name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Order') }}</label>
                                    <input type="number" name="order" class="form-control" value="{{ $class->order }}" min="0">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        {{-- Edit Stream modals --}}
        @foreach ($streams as $stream)
            <div class="modal fade" id="editStreamModal{{ $stream->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.academics.streams.update', $stream) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Stream') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Class') }}</label>
                                    <select name="school_class_id" class="form-select" required>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}" @selected($class->id === $stream->school_class_id)>{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $stream->name }}" required>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Capacity') }}</label>
                                        <input type="number" name="capacity" class="form-control" value="{{ $stream->capacity }}" min="1">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Order') }}</label>
                                        <input type="number" name="sort_order" class="form-control" value="{{ $stream->sort_order }}" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const target = document.querySelector('[name="{{ $errors->keys()[0] ?? '' }}"]')?.closest('.modal');
                    if (target) new bootstrap.Modal(target).show();
                });
            </script>
        @endif

        <script>
            (function () {
                let classIndex = 1;
                document.getElementById('addClassRow')?.addEventListener('click', () => {
                    const row = document.createElement('div');
                    row.className = 'row g-2 mb-2 class-row';
                    row.innerHTML = `
                        <div class="col-8"><input type="text" name="classes[${classIndex}][name]" class="form-control" placeholder="Class name" required></div>
                        <div class="col-3"><input type="number" name="classes[${classIndex}][order]" class="form-control" placeholder="Order" min="0"></div>
                        <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-row" title="Remove row"><i class="bi bi-x-lg"></i></button></div>
                    `;
                    document.getElementById('classRows').appendChild(row);
                    classIndex++;
                });

                let streamIndex = 1;
                document.getElementById('addStreamRow')?.addEventListener('click', () => {
                    const firstSelect = document.querySelector('#newStreamsModal select[name^="streams"]');
                    const sourceOptions = firstSelect?.tomselect
                        ? firstSelect.tomselect.input
                        : firstSelect;
                    const classOptions = Array.from(sourceOptions?.querySelectorAll('option') ?? [])
                        .map(o => `<option value="${o.value}">${o.textContent}</option>`).join('');
                    const row = document.createElement('div');
                    row.className = 'row g-2 mb-2 stream-row';
                    row.innerHTML = `
                        <div class="col-4"><select name="streams[${streamIndex}][school_class_id]" class="form-select" required>${classOptions}</select></div>
                        <div class="col-4"><input type="text" name="streams[${streamIndex}][name]" class="form-control" placeholder="Stream name" required></div>
                        <div class="col-2"><input type="number" name="streams[${streamIndex}][capacity]" class="form-control" placeholder="Capacity" min="1"></div>
                        <div class="col-1"><input type="number" name="streams[${streamIndex}][sort_order]" class="form-control" placeholder="Order" min="0"></div>
                        <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-row" title="Remove row"><i class="bi bi-x-lg"></i></button></div>
                    `;
                    document.getElementById('streamRows').appendChild(row);
                    window.initSearchableSelects?.(row);
                    streamIndex++;
                });

                document.addEventListener('click', (e) => {
                    if (e.target.closest('.remove-row')) {
                        const row = e.target.closest('.class-row, .stream-row');
                        if (row && row.parentElement.children.length > 1) row.remove();
                    }
                });
            })();
        </script>
    @endcan
@endsection
