@extends('layouts.portal')

@section('title', 'Hostels — ElimuGrid')
@section('page-title', __('Hostels'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'hostel'])
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
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search hostel…') }}" value="{{ request('search') }}" style="max-width: 200px;">
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
            <div class="d-flex gap-2">
                <a href="{{ route('school-admin.hostel.rooms') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-door-open me-1"></i> {{ __('Rooms') }}
                </a>
                <a href="{{ route('school-admin.hostel.allocations') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-people me-1"></i> {{ __('Allocations') }}
                </a>
                @can('hostel.manage')
                    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newHostelModal">
                        <i class="bi bi-plus-lg me-1"></i> {{ __('New Hostel') }}
                    </button>
                @endcan
            </div>
        </div>

        @if ($hostels->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-building display-6 d-block mb-2"></i>
                {{ __('No hostels match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Gender') }}</th>
                        <th>{{ __('Warden') }}</th>
                        <th>{{ __('Rooms') }}</th>
                        <th>{{ __('Capacity') }}</th>
                        <th>{{ __('Occupied') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hostels as $hostel)
                        <tr>
                            <td class="text-secondary">{{ $hostels->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $hostel->name }}</td>
                            <td class="text-secondary small">{{ ucfirst($hostel->gender) }}</td>
                            <td class="text-secondary small">{{ $hostel->warden?->name ?? '—' }}</td>
                            <td>{{ $hostel->rooms->count() }}</td>
                            <td>{{ $hostel->rooms->sum('capacity') }}</td>
                            <td>{{ $hostel->rooms->sum('occupied_count') }}</td>
                            <td class="text-end">
                                @can('hostel.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editHostelModal{{ $hostel->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.hostel.destroy', $hostel) }}" class="d-inline"
                                          onsubmit="return confirm('Delete hostel \'{{ $hostel->name }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $hostels])
        @endif
    </div>

    @can('hostel.manage')
        <div class="modal fade" id="newHostelModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.hostel.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Hostel') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('Sunrise House') }}" required>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Gender') }}</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="male">{{ __('Male') }}</option>
                                        <option value="female">{{ __('Female') }}</option>
                                        <option value="mixed">{{ __('Mixed') }}</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Warden') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="warden_user_id" class="form-select">
                                        <option value="">{{ __('Unassigned') }}</option>
                                        @foreach ($wardens as $warden)
                                            <option value="{{ $warden->id }}">{{ $warden->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add Hostel') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newHostelModal')).show();
                });
            </script>
        @endif

        @foreach ($hostels as $hostel)
            <div class="modal fade" id="editHostelModal{{ $hostel->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.hostel.update', $hostel) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Hostel') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $hostel->name }}" required>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Gender') }}</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="male" @selected($hostel->gender === 'male')>{{ __('Male') }}</option>
                                            <option value="female" @selected($hostel->gender === 'female')>{{ __('Female') }}</option>
                                            <option value="mixed" @selected($hostel->gender === 'mixed')>{{ __('Mixed') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Warden') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                        <select name="warden_user_id" class="form-select">
                                            <option value="">{{ __('Unassigned') }}</option>
                                            @foreach ($wardens as $warden)
                                                <option value="{{ $warden->id }}" @selected($hostel->warden_user_id === $warden->id)>{{ $warden->name }}</option>
                                            @endforeach
                                        </select>
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
