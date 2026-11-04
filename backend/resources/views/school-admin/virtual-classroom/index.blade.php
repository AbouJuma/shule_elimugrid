@extends('layouts.portal')

@section('title', 'Virtual Classroom — ElimuGrid')
@section('page-title', __('Virtual Classroom'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'virtual-classroom'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm" style="max-width: 180px;" onchange="this.form.submit()">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="scheduled" @selected(request('status') === 'scheduled')>{{ __('Scheduled') }}</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('Cancelled') }}</option>
                </select>
            </form>
            @can('virtual_classes.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newSessionModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('Schedule Session') }}
                </button>
            @endcan
        </div>

        @if ($classes->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-camera-video display-6 d-block mb-2"></i>
                {{ __('No virtual class sessions match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Class / Subject') }}</th>
                        <th>{{ __('Scheduled') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classes as $class)
                        <tr>
                            <td class="text-secondary">{{ $classes->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">
                                {{ $class->title }}
                                @if ($class->description)
                                    <div class="text-secondary small">{{ $class->description }}</div>
                                @endif
                            </td>
                            <td class="text-secondary small">
                                {{ $class->schoolClass?->name ?? '—' }}
                                @if ($class->subject) / {{ $class->subject->name }} @endif
                            </td>
                            <td class="text-secondary small">{{ $class->scheduled_at->format('d M Y, H:i') }}</td>
                            <td class="text-secondary small">{{ $class->duration_minutes }} min</td>
                            <td>
                                @php
                                    $state = $class->timeState();
                                    $badgeClass = match ($state) {
                                        'cancelled' => 'badge-status-rejected',
                                        'in_progress' => 'badge-status-approved',
                                        'past' => 'badge-status-pending',
                                        default => 'badge-status-approved',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} rounded-pill px-2 py-1">{{ ucfirst(str_replace('_', ' ', $state)) }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    @if ($class->status !== 'cancelled')
                                        <a href="{{ $class->joinUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-camera-video"></i> {{ __('Join') }}
                                        </a>
                                    @endif
                                    @can('virtual_classes.manage')
                                        @if ($class->school_class_id && $class->status !== 'cancelled')
                                            <form method="POST" action="{{ route('school-admin.virtual-classroom.invites', $class) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Send Invites') }}</button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editSessionModal{{ $class->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @if ($class->status !== 'cancelled')
                                            <form method="POST" action="{{ route('school-admin.virtual-classroom.cancel', $class) }}"
                                                  onsubmit="return confirm('Cancel this session?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Cancel') }}</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('school-admin.virtual-classroom.destroy', $class) }}" class="d-inline"
                                                  onsubmit="return confirm('Delete this session?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $classes])
        @endif
    </div>

    @can('virtual_classes.manage')
        <div class="modal fade" id="newSessionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.virtual-classroom.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Schedule Session') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Title') }}</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Description') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Class') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="school_class_id" class="form-select">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($schoolClasses as $schoolClass)
                                            <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Subject') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="subject_id" class="form-select">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Scheduled At') }}</label>
                                    <input type="datetime-local" name="scheduled_at" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Duration (minutes)') }}</label>
                                    <input type="number" min="15" max="480" name="duration_minutes" class="form-control" value="60" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Schedule') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newSessionModal')).show();
                });
            </script>
        @endif

        @foreach ($classes as $class)
            <div class="modal fade" id="editSessionModal{{ $class->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.virtual-classroom.update', $class) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Session') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Title') }}</label>
                                    <input type="text" name="title" class="form-control" value="{{ $class->title }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Description') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <textarea name="description" class="form-control" rows="2">{{ $class->description }}</textarea>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Class') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                        <select name="school_class_id" class="form-select">
                                            <option value="">{{ __('None') }}</option>
                                            @foreach ($schoolClasses as $schoolClass)
                                                <option value="{{ $schoolClass->id }}" @selected($schoolClass->id === $class->school_class_id)>{{ $schoolClass->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Subject') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                        <select name="subject_id" class="form-select">
                                            <option value="">{{ __('None') }}</option>
                                            @foreach ($subjects as $subject)
                                                <option value="{{ $subject->id }}" @selected($subject->id === $class->subject_id)>{{ $subject->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Scheduled At') }}</label>
                                        <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ $class->scheduled_at->format('Y-m-d\TH:i') }}" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Duration (minutes)') }}</label>
                                        <input type="number" min="15" max="480" name="duration_minutes" class="form-control" value="{{ $class->duration_minutes }}" required>
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
    @endcan
@endsection
