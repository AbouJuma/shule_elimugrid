@extends('layouts.portal')

@section('title', 'Invoices — ElimuGrid')
@section('page-title', __('Invoices'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'invoices'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    <div class="card">
        <div class="card-header bg-white">
            <form method="GET" class="d-flex gap-2" style="max-width: 320px;">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="unpaid" @selected(request('status')==='unpaid')>{{ __('Unpaid') }}</option>
                    <option value="partial" @selected(request('status')==='partial')>{{ __('Partial') }}</option>
                    <option value="paid" @selected(request('status')==='paid')>{{ __('Paid') }}</option>
                </select>
            </form>
        </div>

        @if ($invoices->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                {{ __('No invoices yet — generate some from a') }} <a href="{{ route('school-admin.fee-structures.index') }}">{{ __('fee structure') }}</a>.
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th style="width: 1%;">#</th><th>{{ __('Invoice No.') }}</th><th>{{ __('Student') }}</th><th>{{ __('Total') }}</th><th>{{ __('Balance') }}</th><th>{{ __('Status') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('school-admin.invoices.show', $invoice) }}'" style="cursor:pointer">
                            <td class="text-secondary">{{ $invoices->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->student?->fullName() ?? __('(deleted student)') }}</td>
                            <td>{{ number_format($invoice->total_amount, 2) }}</td>
                            <td>{{ number_format($invoice->balance(), 2) }}</td>
                            <td>
                                <span class="badge {{ $invoice->status === 'paid' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $invoices])
        @endif
    </div>
@endsection
