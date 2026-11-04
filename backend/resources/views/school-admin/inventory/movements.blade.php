@extends('layouts.portal')

@section('title', $item->name.' — ElimuGrid')
@section('page-title', $item->name)

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'inventory'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <a href="{{ route('school-admin.inventory.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Inventory') }}
    </a>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white">{{ __('Movement History') }}</div>
                @if ($movements->isEmpty())
                    <div class="card-body text-center text-secondary py-4">{{ __('No movements recorded yet.') }}</div>
                @else
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Reason') }}</th><th>{{ __('By') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($movements as $movement)
                                <tr>
                                    <td class="text-secondary small">{{ $movement->moved_at->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge {{ $movement->type === 'in' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                                            {{ $movement->type === 'in' ? 'Stock In' : 'Stock Out' }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($movement->quantity, 2) }} {{ $item->unit }}</td>
                                    <td class="text-secondary small">{{ $movement->reason ?? '—' }}</td>
                                    <td class="text-secondary small">{{ $movement->recordedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @include('school-admin.partials.table-pagination', ['paginator' => $movements])
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="text-secondary small">{{ __('Currently on hand') }}</div>
                    <div class="value">{{ number_format($item->quantity_on_hand, 2) }} {{ $item->unit }}</div>
                </div>
            </div>

            @can('inventory.manage')
                <div class="card">
                    <div class="card-header bg-white">{{ __('Record a Movement') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('school-admin.inventory.movements.store') }}">
                            @csrf
                            <input type="hidden" name="inventory_item_id" value="{{ $item->id }}">
                            <div class="mb-3">
                                <label class="form-label d-block">{{ __('Type') }}</label>
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="type" id="typeIn" value="in" checked>
                                    <label class="btn btn-outline-secondary btn-sm" for="typeIn">{{ __('Stock In') }}</label>
                                    <input type="radio" class="btn-check" name="type" id="typeOut" value="out">
                                    <label class="btn btn-outline-secondary btn-sm" for="typeOut">{{ __('Stock Out') }}</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity ({{ $item->unit }})</label>
                                <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Reason') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <input type="text" name="reason" class="form-control" placeholder="{{ __('Issued to Form 1') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Date') }}</label>
                                <input type="date" name="moved_at" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <button type="submit" class="btn btn-gold w-100">{{ __('Record Movement') }}</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
