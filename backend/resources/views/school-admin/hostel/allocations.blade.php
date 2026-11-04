@extends('layouts.portal')

@section('title', 'Hostel Allocations — ElimuGrid')
@section('page-title', __('Hostel Allocations'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'hostel-allocations'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    <a href="{{ route('school-admin.hostel.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Hostels') }}
    </a>

    <div class="card">
        <div class="card-header bg-white">
            <form method="GET">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option>
                    <option value="vacated" @selected(request('status')==='vacated')>{{ __('Vacated') }}</option>
                </select>
            </form>
        </div>

        @if ($allocations->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No allocations match these filters.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Student') }}</th>
                        <th>{{ __('Hostel / Room') }}</th>
                        <th>{{ __('Allocated') }}</th>
                        <th>{{ __('Vacated') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allocations as $allocation)
                        <tr>
                            <td class="text-secondary">{{ $allocations->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $allocation->student->fullName() }}</td>
                            <td class="text-secondary small">{{ $allocation->room->hostel->name }} / {{ $allocation->room->room_number }}</td>
                            <td class="text-secondary small">{{ $allocation->allocated_date->format('d M Y') }}</td>
                            <td class="text-secondary small">{{ $allocation->vacated_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $allocation->status === 'active' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($allocation->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('hostel.manage')
                                    @if ($allocation->status === 'active')
                                        <form method="POST" action="{{ route('school-admin.hostel.allocations.vacate', $allocation) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Vacate') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $allocations])
        @endif
    </div>
@endsection
