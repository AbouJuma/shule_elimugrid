@extends('layouts.portal')

@section('title', 'Leave — ElimuGrid')
@section('page-title', __('Leave'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'leave'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white">{{ __('My Requests') }}</div>
                @if ($myRequests->isEmpty())
                    <div class="card-body text-center text-secondary py-4">{{ __('You haven\'t requested any leave yet.') }}</div>
                @else
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($myRequests as $req)
                                <tr>
                                    <td>{{ $req->leaveType->name }}</td>
                                    <td class="text-secondary small">{{ $req->start_date->format('d M') }} – {{ $req->end_date->format('d M Y') }}</td>
                                    <td>{{ $req->days }}</td>
                                    <td>
                                        @php
                                            $badge = match ($req->status) {
                                                'approved' => 'badge-status-active',
                                                'rejected' => 'badge-status-suspended',
                                                default => 'badge-status-pending',
                                            };
                                        @endphp
                                        <span class="badge {{ $badge }} rounded-pill px-3 py-2">{{ ucfirst($req->status) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white">{{ __('Request Leave') }}</div>
                <div class="card-body">
                    @if ($leaveTypes->isEmpty())
                        <p class="text-secondary small mb-0">{{ __('No leave types have been set up yet.') }}</p>
                    @else
                        <form method="POST" action="{{ route('school-admin.leave.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">{{ __('Leave Type') }}</label>
                                <select name="leave_type_id" class="form-select" required>
                                    @foreach ($leaveTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->days_per_year }} days/year)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Start Date') }}</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('End Date') }}</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Reason') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <textarea name="reason" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-gold w-100">{{ __('Submit Request') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('hr.manage')
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>{{ __('Leave Types') }}</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#newLeaveTypeModal">
                    <i class="bi bi-plus-lg"></i> {{ __('New Type') }}
                </button>
            </div>
            <div class="card-body">
                @if ($leaveTypes->isEmpty())
                    <p class="text-secondary small mb-0">{{ __('No leave types yet.') }}</p>
                @else
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($leaveTypes as $type)
                            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                                {{ $type->name }} — {{ $type->days_per_year }} days/year@unless ($type->is_paid) · {{ __('unpaid') }}@endunless
                                <button type="button" class="btn btn-link btn-sm p-0 text-secondary" data-bs-toggle="modal" data-bs-target="#editLeaveTypeModal{{ $type->id }}">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form method="POST" action="{{ route('school-admin.leave.types.destroy', $type) }}" class="d-inline"
                                      onsubmit="return confirm('Delete leave type \'{{ $type->name }}\'?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endcan

    @if ($canApprove)
        <div class="card">
            <div class="card-header bg-white">
                <form method="GET">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="pending" @selected(request('status')==='pending')>{{ __('Pending') }}</option>
                        <option value="approved" @selected(request('status')==='approved')>{{ __('Approved') }}</option>
                        <option value="rejected" @selected(request('status')==='rejected')>{{ __('Rejected') }}</option>
                    </select>
                </form>
            </div>
            @if ($pendingQueue->isEmpty())
                <div class="card-body text-center text-secondary py-4">{{ __('No leave requests match these filters.') }}</div>
            @else
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 1%;">#</th>
                            <th>{{ __('Staff') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Dates') }}</th>
                            <th>{{ __('Days') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingQueue as $req)
                            <tr>
                                <td class="text-secondary">{{ $pendingQueue->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $req->user->name }}</td>
                                <td>{{ $req->leaveType->name }}</td>
                                <td class="text-secondary small">{{ $req->start_date->format('d M') }} – {{ $req->end_date->format('d M Y') }}</td>
                                <td>{{ $req->days }}</td>
                                <td>
                                    @php
                                        $badge = match ($req->status) {
                                            'approved' => 'badge-status-active',
                                            'rejected' => 'badge-status-suspended',
                                            default => 'badge-status-pending',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }} rounded-pill px-3 py-2">{{ ucfirst($req->status) }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($req->status === 'pending')
                                        <form method="POST" action="{{ route('school-admin.leave.approve', $req) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('school-admin.leave.reject', $req) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Reject') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @include('school-admin.partials.table-pagination', ['paginator' => $pendingQueue])
            @endif
        </div>
    @endif

    @can('hr.manage')
        <div class="modal fade" id="newLeaveTypeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.leave.types.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Leave Type') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('Annual Leave') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Days per year') }}</label>
                                <input type="number" min="1" max="365" name="days_per_year" class="form-control" required>
                            </div>
                            <div class="form-check mb-3">
                                <input type="hidden" name="is_paid" value="0">
                                <input class="form-check-input" type="checkbox" name="is_paid" value="1" id="paidNew" checked>
                                <label class="form-check-label" for="paidNew">{{ __('Paid leave (unpaid leave is deducted from salary)') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @foreach ($leaveTypes as $type)
            <div class="modal fade" id="editLeaveTypeModal{{ $type->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.leave.types.update', $type) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Leave Type') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $type->name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Days per year') }}</label>
                                    <input type="number" min="1" max="365" name="days_per_year" class="form-control" value="{{ $type->days_per_year }}" required>
                                </div>
                                <div class="form-check mb-3">
                                    <input type="hidden" name="is_paid" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_paid" value="1" id="paid{{ $type->id }}" @checked($type->is_paid)>
                                    <label class="form-check-label" for="paid{{ $type->id }}">{{ __('Paid leave (unpaid leave is deducted from salary)') }}</label>
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
