@extends('layouts.portal')

@section('title', 'Transport Routes — ElimuGrid')
@section('page-title', __('Transport Routes'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'transport-routes'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected(request('status')==='inactive')>{{ __('Inactive') }}</option>
                </select>
            </form>
            @can('transport.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newRouteModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Route') }}
                </button>
            @endcan
        </div>

        @if ($routes->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-signpost-split display-6 d-block mb-2"></i>
                {{ __('No transport routes match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Vehicle') }}</th>
                        <th>{{ __('Stops') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($routes as $route)
                        <tr>
                            <td class="text-secondary">{{ $routes->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $route->name }}</td>
                            <td class="text-secondary small">{{ $route->vehicle?->registration_number ?? '—' }}</td>
                            <td class="text-secondary small">{{ $route->stops->pluck('name')->implode(' → ') ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $route->status === 'active' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($route->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('transport.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#editRouteModal{{ $route->id }}">
                                        {{ __('Edit') }}
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.transport-routes.destroy', $route) }}" class="d-inline"
                                          onsubmit="return confirm('Delete route \'{{ $route->name }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $routes])
        @endif
    </div>

    @can('transport.manage')
        @foreach ($routes as $route)
            <div class="modal fade" id="editRouteModal{{ $route->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.transport-routes.update', $route) }}" class="route-form">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title h5">Edit Route — {{ $route->name }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-7">
                                        <label class="form-label">{{ __('Route Name') }}</label>
                                        <input type="text" name="name" class="form-control" value="{{ $route->name }}" required>
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label">{{ __('Vehicle') }}</label>
                                        <select name="vehicle_id" class="form-select">
                                            <option value="">{{ __('Unassigned') }}</option>
                                            @foreach ($vehicles as $vehicle)
                                                <option value="{{ $vehicle->id }}" @selected($route->vehicle_id === $vehicle->id)>{{ $vehicle->registration_number }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Status') }}</label>
                                    <select name="status" class="form-select" style="max-width: 200px;">
                                        <option value="active" @selected($route->status==='active')>{{ __('Active') }}</option>
                                        <option value="inactive" @selected($route->status==='inactive')>{{ __('Inactive') }}</option>
                                    </select>
                                </div>

                                <label class="form-label d-block">{{ __('Stops (in order)') }}</label>
                                <div class="stop-rows">
                                    @foreach ($route->stops as $i => $stop)
                                        <div class="border rounded p-2 mb-2 stop-row">
                                            <div class="row g-2 align-items-center mb-2">
                                                <div class="col-7"><input type="text" name="stops[{{ $i }}][name]" class="form-control form-control-sm" placeholder="{{ __('Stop name') }}" value="{{ $stop->name }}" required></div>
                                                <div class="col-3"><input type="number" min="1" name="stops[{{ $i }}][sequence]" class="form-control form-control-sm" placeholder="{{ __('Order') }}" value="{{ $stop->sequence }}" required></div>
                                                <div class="col-2 text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-stop-row"><i class="bi bi-x"></i></button></div>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-6 position-relative">
                                                    <input type="text" class="form-control form-control-sm place-search-input" placeholder="{{ __('Search place name (Tanzania)…') }}" autocomplete="off">
                                                    <div class="list-group place-search-results position-absolute w-100 shadow-sm" style="z-index: 1060; display: none; max-height: 200px; overflow-y: auto;"></div>
                                                </div>
                                                <div class="col-3"><input type="number" step="any" name="stops[{{ $i }}][latitude]" class="form-control form-control-sm stop-lat" placeholder="{{ __('Latitude') }}" value="{{ $stop->latitude }}"></div>
                                                <div class="col-3"><input type="number" step="any" name="stops[{{ $i }}][longitude]" class="form-control form-control-sm stop-lng" placeholder="{{ __('Longitude') }}" value="{{ $stop->longitude }}"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm add-stop-row">
                                    <i class="bi bi-plus-lg"></i> {{ __('Add Stop') }}
                                </button>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        <div class="modal fade" id="newRouteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('school-admin.transport-routes.store') }}" class="route-form">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Transport Route') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
                            @endif
                            <div class="row g-3 mb-3">
                                <div class="col-7">
                                    <label class="form-label">{{ __('Route Name') }}</label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ __('Route A - Town') }}" required>
                                </div>
                                <div class="col-5">
                                    <label class="form-label">{{ __('Vehicle') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="vehicle_id" class="form-select">
                                        <option value="">{{ __('Unassigned') }}</option>
                                        @foreach ($vehicles as $vehicle)
                                            <option value="{{ $vehicle->id }}">{{ $vehicle->registration_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <label class="form-label d-block">{{ __('Stops (in order)') }}</label>
                            <div class="stop-rows">
                                <div class="border rounded p-2 mb-2 stop-row">
                                    <div class="row g-2 align-items-center mb-2">
                                        <div class="col-7"><input type="text" name="stops[0][name]" class="form-control form-control-sm" placeholder="{{ __('Stop name') }}" required></div>
                                        <div class="col-3"><input type="number" min="1" name="stops[0][sequence]" class="form-control form-control-sm" placeholder="{{ __('Order') }}" value="1" required></div>
                                        <div class="col-2"></div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6 position-relative">
                                            <input type="text" class="form-control form-control-sm place-search-input" placeholder="{{ __('Search place name (Tanzania)…') }}" autocomplete="off">
                                            <div class="list-group place-search-results position-absolute w-100 shadow-sm" style="z-index: 1060; display: none; max-height: 200px; overflow-y: auto;"></div>
                                        </div>
                                        <div class="col-3"><input type="number" step="any" name="stops[0][latitude]" class="form-control form-control-sm stop-lat" placeholder="{{ __('Latitude') }}"></div>
                                        <div class="col-3"><input type="number" step="any" name="stops[0][longitude]" class="form-control form-control-sm stop-lng" placeholder="{{ __('Longitude') }}"></div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm add-stop-row">
                                <i class="bi bi-plus-lg"></i> {{ __('Add Stop') }}
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create Route') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        <script>
            (function () {
                function stopRowHtml(index) {
                    return '' +
                        '<div class="row g-2 align-items-center mb-2">' +
                            '<div class="col-7"><input type="text" name="stops[' + index + '][name]" class="form-control form-control-sm" placeholder="Stop name" required></div>' +
                            '<div class="col-3"><input type="number" min="1" name="stops[' + index + '][sequence]" class="form-control form-control-sm" placeholder="Order" value="' + (index + 1) + '" required></div>' +
                            '<div class="col-2 text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-stop-row"><i class="bi bi-x"></i></button></div>' +
                        '</div>' +
                        '<div class="row g-2">' +
                            '<div class="col-6 position-relative">' +
                                '<input type="text" class="form-control form-control-sm place-search-input" placeholder="Search place name (Tanzania)…" autocomplete="off">' +
                                '<div class="list-group place-search-results position-absolute w-100 shadow-sm" style="z-index: 1060; display: none; max-height: 200px; overflow-y: auto;"></div>' +
                            '</div>' +
                            '<div class="col-3"><input type="number" step="any" name="stops[' + index + '][latitude]" class="form-control form-control-sm stop-lat" placeholder="Latitude"></div>' +
                            '<div class="col-3"><input type="number" step="any" name="stops[' + index + '][longitude]" class="form-control form-control-sm stop-lng" placeholder="Longitude"></div>' +
                        '</div>';
                }

                document.querySelectorAll('.route-form').forEach(function (form) {
                    var container = form.querySelector('.stop-rows');
                    var addButton = form.querySelector('.add-stop-row');
                    var index = container.querySelectorAll('.stop-row').length;

                    addButton.addEventListener('click', function () {
                        var row = document.createElement('div');
                        row.className = 'border rounded p-2 mb-2 stop-row';
                        row.innerHTML = stopRowHtml(index);
                        container.appendChild(row);
                        index++;
                    });

                    container.addEventListener('click', function (event) {
                        var removeButton = event.target.closest('.remove-stop-row');
                        if (removeButton) {
                            removeButton.closest('.stop-row').remove();
                            return;
                        }

                        var result = event.target.closest('.place-search-results .list-group-item');
                        if (result) {
                            event.preventDefault();
                            var row = result.closest('.stop-row');
                            row.querySelector('.place-search-input').value = result.dataset.name;
                            row.querySelector('.stop-lat').value = result.dataset.lat;
                            row.querySelector('.stop-lng').value = result.dataset.lng;
                            result.closest('.place-search-results').style.display = 'none';
                        }
                    });

                    var searchTimer = null;
                    container.addEventListener('input', function (event) {
                        if (!event.target.classList.contains('place-search-input')) return;

                        var input = event.target;
                        var resultsBox = input.parentElement.querySelector('.place-search-results');
                        var query = input.value.trim();

                        clearTimeout(searchTimer);
                        if (query.length < 3) {
                            resultsBox.style.display = 'none';
                            return;
                        }

                        searchTimer = setTimeout(function () {
                            fetch('/api/v1/places/search?q=' + encodeURIComponent(query), {
                                headers: {'Accept': 'application/json'},
                                credentials: 'same-origin',
                            })
                                .then(function (response) { return response.ok ? response.json() : {data: []}; })
                                .then(function (body) {
                                    var places = body.data || [];
                                    if (!places.length) {
                                        resultsBox.style.display = 'none';
                                        resultsBox.innerHTML = '';
                                        return;
                                    }
                                    resultsBox.innerHTML = places.map(function (place) {
                                        return '<button type="button" class="list-group-item list-group-item-action py-1 px-2 small" ' +
                                            'data-name="' + place.name.replace(/"/g, '&quot;') + '" data-lat="' + place.latitude + '" data-lng="' + place.longitude + '">' +
                                            place.name + '</button>';
                                    }).join('');
                                    resultsBox.style.display = 'block';
                                })
                                .catch(function () { resultsBox.style.display = 'none'; });
                        }, 400);
                    });

                    document.addEventListener('click', function (event) {
                        if (event.target.closest('.place-search-input') || event.target.closest('.place-search-results')) return;
                        container.querySelectorAll('.place-search-results').forEach(function (box) { box.style.display = 'none'; });
                    });
                });
            })();
        </script>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newRouteModal')).show();
                });
            </script>
        @endif
    @endcan
@endsection
