@extends('layouts.portal')

@section('title', 'Procurement — ElimuGrid')
@section('page-title', __('Procurement'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'procurement'])
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
                    <div class="card-body text-center text-secondary py-4">{{ __('You haven\'t submitted any purchase requests yet.') }}</div>
                @else
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Items') }}</th><th>{{ __('Status') }}</th><th>{{ __('Submitted') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($myRequests as $req)
                                <tr>
                                    <td class="small">{{ $req->items->pluck('description')->implode(', ') }}</td>
                                    <td>
                                        @php
                                            $badge = match ($req->status) {
                                                'approved' => 'badge-status-active',
                                                'received' => 'badge-status-active',
                                                'rejected' => 'badge-status-suspended',
                                                default => 'badge-status-pending',
                                            };
                                        @endphp
                                        <span class="badge {{ $badge }} rounded-pill px-3 py-2">{{ ucfirst($req->status) }}</span>
                                    </td>
                                    <td class="text-secondary small">{{ $req->created_at->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white">{{ __('New Request') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('school-admin.procurement.store') }}" id="procurementForm">
                        @csrf
                        <div id="itemRows">
                            <div class="row g-2 mb-2 item-row">
                                <div class="col-6"><input type="text" name="items[0][description]" class="form-control form-control-sm" placeholder="{{ __('Item description') }}" required></div>
                                <div class="col-3"><input type="number" step="0.01" min="0.01" name="items[0][quantity]" class="form-control form-control-sm" placeholder="{{ __('Qty') }}" required></div>
                                <div class="col-3"><input type="number" step="0.01" min="0" name="items[0][estimated_cost]" class="form-control form-control-sm" placeholder="{{ __('Est. cost') }}"></div>
                            </div>
                        </div>
                        <button type="button" id="addItemRow" class="btn btn-outline-secondary btn-sm mb-3">
                            <i class="bi bi-plus-lg"></i> {{ __('Add Item') }}
                        </button>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-gold w-100">{{ __('Submit Request') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($canApprove)
        <div class="card">
            <div class="card-header bg-white">
                <form method="GET">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="pending" @selected(request('status')==='pending')>{{ __('Pending') }}</option>
                        <option value="approved" @selected(request('status')==='approved')>{{ __('Approved') }}</option>
                        <option value="rejected" @selected(request('status')==='rejected')>{{ __('Rejected') }}</option>
                        <option value="received" @selected(request('status')==='received')>{{ __('Received') }}</option>
                    </select>
                </form>
            </div>
            @if ($queue->isEmpty())
                <div class="card-body text-center text-secondary py-4">{{ __('No purchase requests match these filters.') }}</div>
            @else
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 1%;">#</th>
                            <th>{{ __('Requested By') }}</th>
                            <th>{{ __('Items') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($queue as $req)
                            <tr>
                                <td class="text-secondary">{{ $queue->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $req->requestedBy->name }}</td>
                                <td class="small">{{ $req->items->pluck('description')->implode(', ') }}</td>
                                <td>
                                    @php
                                        $badge = match ($req->status) {
                                            'approved' => 'badge-status-active',
                                            'received' => 'badge-status-active',
                                            'rejected' => 'badge-status-suspended',
                                            default => 'badge-status-pending',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }} rounded-pill px-3 py-2">{{ ucfirst($req->status) }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($req->status === 'pending')
                                        <form method="POST" action="{{ route('school-admin.procurement.approve', $req) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('school-admin.procurement.reject', $req) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Reject') }}</button>
                                        </form>
                                    @elseif ($req->status === 'approved')
                                        <form method="POST" action="{{ route('school-admin.procurement.receive', $req) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Mark Received') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @include('school-admin.partials.table-pagination', ['paginator' => $queue])
            @endif
        </div>
    @endif

    <script>
        (function () {
            var container = document.getElementById('itemRows');
            var addButton = document.getElementById('addItemRow');
            if (!container || !addButton) return;

            var index = 1;
            addButton.addEventListener('click', function () {
                var row = document.createElement('div');
                row.className = 'row g-2 mb-2 item-row';
                row.innerHTML =
                    '<div class="col-6"><input type="text" name="items[' + index + '][description]" class="form-control form-control-sm" placeholder="Item description" required></div>' +
                    '<div class="col-3"><input type="number" step="0.01" min="0.01" name="items[' + index + '][quantity]" class="form-control form-control-sm" placeholder="Qty" required></div>' +
                    '<div class="col-3"><input type="number" step="0.01" min="0" name="items[' + index + '][estimated_cost]" class="form-control form-control-sm" placeholder="Est. cost"></div>';
                container.appendChild(row);
                index++;
            });
        })();
    </script>
@endsection
