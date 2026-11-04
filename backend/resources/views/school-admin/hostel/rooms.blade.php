@extends('layouts.portal')

@section('title', 'Hostel Rooms — ElimuGrid')
@section('page-title', __('Hostel Rooms'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'hostel-rooms'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <a href="{{ route('school-admin.hostel.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Hostels') }}
    </a>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET">
                <select name="hostel_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 200px;">
                    <option value="">{{ __('All hostels') }}</option>
                    @foreach ($hostels as $hostel)
                        <option value="{{ $hostel->id }}" @selected(request('hostel_id') == $hostel->id)>{{ $hostel->name }}</option>
                    @endforeach
                </select>
            </form>
            @can('hostel.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newRoomModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Room') }}
                </button>
            @endcan
        </div>

        @if ($rooms->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-door-open display-6 d-block mb-2"></i>
                {{ __('No rooms match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Hostel') }}</th>
                        <th>{{ __('Room') }}</th>
                        <th>{{ __('Occupancy') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rooms as $room)
                        <tr>
                            <td class="text-secondary">{{ $rooms->firstItem() + $loop->index }}</td>
                            <td class="text-secondary small">{{ $room->hostel->name }}</td>
                            <td class="fw-semibold">{{ $room->room_number }}</td>
                            <td>
                                {{ $room->occupied_count }} / {{ $room->capacity }}
                                @if ($room->isFull())
                                    <span class="badge badge-status-pending rounded-pill px-2 py-1 ms-1">{{ __('Full') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('hostel.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#allocateModal{{ $room->id }}"
                                            @disabled($room->isFull())>
                                        {{ __('Allocate') }}
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editRoomModal{{ $room->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.hostel.rooms.destroy', $room) }}" class="d-inline"
                                          onsubmit="return confirm('Delete room \'{{ $room->room_number }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $rooms])
        @endif
    </div>

    @can('hostel.manage')
        @foreach ($rooms as $room)
            <div class="modal fade" id="allocateModal{{ $room->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.hostel.rooms.allocate', $room) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5">Allocate — {{ $room->hostel->name }} / {{ $room->room_number }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Student') }}</label>
                                    <select name="student_id" class="form-select" required>
                                        <option value="">{{ __('Select student…') }}</option>
                                        @foreach ($students as $student)
                                            <option value="{{ $student->id }}">{{ $student->fullName() }} ({{ $student->admission_number }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Allocate') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        @foreach ($rooms as $room)
            <div class="modal fade" id="editRoomModal{{ $room->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.hostel.rooms.update', $room) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Room') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Hostel') }}</label>
                                    <select name="hostel_id" class="form-select" required>
                                        @foreach ($hostels as $hostel)
                                            <option value="{{ $hostel->id }}" @selected($hostel->id === $room->hostel_id)>{{ $hostel->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Room Number') }}</label>
                                        <input type="text" name="room_number" class="form-control" value="{{ $room->room_number }}" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Capacity') }}</label>
                                        <input type="number" min="1" name="capacity" class="form-control" value="{{ $room->capacity }}" required>
                                    </div>
                                </div>
                                <p class="text-secondary small mt-3 mb-0">Currently {{ $room->occupied_count }} student(s) allocated.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        <div class="modal fade" id="newRoomModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.hostel.rooms.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Room') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Hostel') }}</label>
                                <select name="hostel_id" class="form-select" required>
                                    <option value="">{{ __('Select hostel…') }}</option>
                                    @foreach ($hostels as $hostel)
                                        <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Room Number') }}</label>
                                    <input type="text" name="room_number" class="form-control" placeholder="A-101" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Capacity') }}</label>
                                    <input type="number" min="1" name="capacity" class="form-control" value="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add Room') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newRoomModal')).show();
                });
            </script>
        @endif
    @endcan
@endsection
