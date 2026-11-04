@extends('layouts.portal')

@section('title', 'Vehicles — ElimuGrid')
@section('page-title', __('Vehicles'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'vehicles'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search registration no…') }}" value="{{ request('search') }}" style="max-width: 200px;">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option>
                    <option value="maintenance" @selected(request('status')==='maintenance')>{{ __('Maintenance') }}</option>
                    <option value="inactive" @selected(request('status')==='inactive')>{{ __('Inactive') }}</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
            @can('transport.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newVehicleModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Vehicle') }}
                </button>
            @endcan
        </div>

        @if ($vehicles->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-truck-front display-6 d-block mb-2"></i>
                {{ __('No vehicles match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Registration No.') }}</th>
                        <th>{{ __('Make / Model') }}</th>
                        <th>{{ __('Capacity') }}</th>
                        <th>{{ __('Driver') }}</th>
                        <th>{{ __('Last Location') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicles as $vehicle)
                        <tr>
                            <td class="text-secondary">{{ $vehicles->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $vehicle->registration_number }}</td>
                            <td class="text-secondary small">{{ trim(($vehicle->make ?? '').' '.($vehicle->model ?? '')) ?: '—' }}</td>
                            <td>{{ $vehicle->capacity ?? '—' }}</td>
                            <td>{{ $vehicle->driver?->name ?? '—' }}</td>
                            <td class="text-secondary small">
                                @if ($vehicle->last_location_at)
                                    {{ $vehicle->last_location_at->diffForHumans() }}
                                @else
                                    Never reported
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $vehicle->status === 'active' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($vehicle->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('transport.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#editVehicleModal{{ $vehicle->id }}">
                                        {{ __('Edit') }}
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.vehicles.destroy', $vehicle) }}" class="d-inline"
                                          onsubmit="return confirm('Delete vehicle \'{{ $vehicle->registration_number }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $vehicles])
        @endif
    </div>

    @can('transport.manage')
        @foreach ($vehicles as $vehicle)
            <div class="modal fade" id="editVehicleModal{{ $vehicle->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.vehicles.update', $vehicle) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title h5">Edit Vehicle — {{ $vehicle->registration_number }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Registration Number') }}</label>
                                    <input type="text" name="registration_number" class="form-control" value="{{ $vehicle->registration_number }}" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Make') }}</label>
                                        <input type="text" name="make" class="form-control" value="{{ $vehicle->make }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Model') }}</label>
                                        <input type="text" name="model" class="form-control" value="{{ $vehicle->model }}">
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Capacity') }}</label>
                                        <input type="number" min="1" name="capacity" class="form-control" value="{{ $vehicle->capacity }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Status') }}</label>
                                        <select name="status" class="form-select">
                                            <option value="active" @selected($vehicle->status==='active')>{{ __('Active') }}</option>
                                            <option value="maintenance" @selected($vehicle->status==='maintenance')>{{ __('Maintenance') }}</option>
                                            <option value="inactive" @selected($vehicle->status==='inactive')>{{ __('Inactive') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Driver') }}</label>
                                    <select name="driver_user_id" class="form-select">
                                        <option value="">{{ __('Unassigned') }}</option>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}" @selected($vehicle->driver_user_id === $driver->id)>{{ $driver->name }}</option>
                                        @endforeach
                                    </select>
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

        <div class="modal fade" id="newVehicleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.vehicles.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Vehicle') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">{{ __('Registration Number') }}</label>
                                <input type="text" name="registration_number" class="form-control" placeholder="{{ __('KDA 123A') }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Make') }}</label>
                                    <input type="text" name="make" class="form-control" placeholder="{{ __('Toyota') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Model') }}</label>
                                    <input type="text" name="model" class="form-control" placeholder="{{ __('Coaster') }}">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Capacity') }}</label>
                                    <input type="number" min="1" name="capacity" class="form-control" placeholder="30">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Driver') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="driver_user_id" class="form-select">
                                        <option value="">{{ __('Unassigned') }}</option>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add Vehicle') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newVehicleModal')).show();
                });
            </script>
        @endif
    @endcan
@endsection
