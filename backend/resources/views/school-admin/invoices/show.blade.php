@extends('layouts.portal')

@section('title', $invoice->invoice_number.' — ElimuGrid')
@section('page-title', $invoice->invoice_number)

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'invoices'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <a href="{{ route('school-admin.invoices.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Invoices') }}
    </a>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>{{ $invoice->student?->fullName() ?? __('(deleted student)') }} — {{ $invoice->student?->admission_number ?? '—' }}</span>
                    <span class="badge {{ $invoice->status === 'paid' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-3">
                        <dt class="col-5 text-secondary">{{ __('Control Number') }}</dt><dd class="col-7">{{ $invoice->control_number }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Issued') }}</dt><dd class="col-7">{{ $invoice->issued_date->format('d M Y') }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Due') }}</dt><dd class="col-7">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</dd>
                        @if ($invoice->bankAccount)
                            <dt class="col-5 text-secondary">{{ __('Pay to') }}</dt>
                            <dd class="col-7">{{ $invoice->bankAccount->bank_name }} — {{ $invoice->bankAccount->account_number }}</dd>
                        @endif
                    </dl>

                    <table class="table table-sm">
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="fw-semibold border-top">
                                <td>{{ __('Total') }}</td>
                                <td class="text-end">{{ number_format($invoice->total_amount, 2) }}</td>
                            </tr>
                            <tr class="text-success">
                                <td>{{ __('Paid') }}</td>
                                <td class="text-end">{{ number_format($invoice->amount_paid, 2) }}</td>
                            </tr>
                            <tr class="fw-semibold">
                                <td>{{ __('Balance') }}</td>
                                <td class="text-end">{{ number_format($invoice->balance(), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">{{ __('Payment History') }}</div>
                @if ($invoice->payments->isEmpty())
                    <div class="card-body text-center text-secondary py-4">{{ __('No payments recorded yet.') }}</div>
                @else
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Receipt') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Method') }}</th><th>{{ __('Date') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->payments as $payment)
                                <tr>
                                    <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                                    <td>{{ number_format($payment->amount, 2) }}</td>
                                    <td class="text-secondary small">{{ str_replace('_', ' ', ucfirst($payment->method)) }}</td>
                                    <td class="text-secondary small">{{ $payment->paid_on->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($invoice->gatewayTransactions->isNotEmpty())
                <div class="card mt-4">
                    <div class="card-header bg-white">{{ __('Payment Attempts') }} <span class="text-secondary small">({{ __('mobile money / bank') }})</span></div>
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Provider') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('When') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->gatewayTransactions as $t)
                                <tr>
                                    <td class="small">{{ $t->provider }} <span class="text-secondary">({{ in_array($t->provider, ['CRDB', 'NMB']) ? __('bank') : __('mobile money') }})</span></td>
                                    <td class="small">{{ $t->msisdn }}</td>
                                    <td class="small">{{ number_format($t->amount, 2) }}</td>
                                    <td>
                                        @php
                                            $gBadge = match ($t->status) {
                                                'completed' => 'badge-status-active',
                                                'failed' => 'badge-status-suspended',
                                                default => 'badge-status-pending',
                                            };
                                        @endphp
                                        <span class="badge {{ $gBadge }} rounded-pill px-2 py-1">{{ ucfirst($t->status) }}</span>
                                        @if ($t->status === 'failed' && $t->failure_reason)
                                            <div class="text-danger small mt-1">{{ \Illuminate\Support\Str::limit($t->failure_reason, 50) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-secondary small">{{ $t->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            @can('communications.send')
                @if ($invoice->status !== 'paid')
                    <div class="card mb-4">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold small">{{ __('Remind guardian by SMS') }}</div>
                                <div class="text-secondary small">Balance: {{ number_format($invoice->balance(), 2) }}</div>
                            </div>
                            <form method="POST" action="{{ route('school-admin.invoices.reminder.store', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-chat-dots me-1"></i> {{ __('Send Reminder') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan
            @can('fees.collect')
                @if ($invoice->status !== 'paid')
                    <div class="card">
                        <div class="card-header bg-white">{{ __('Record Payment') }}</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('school-admin.invoices.payments.store', $invoice) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Amount') }}</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                                    <div class="form-text">Outstanding balance: {{ number_format($invoice->balance(), 2) }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Method') }}</label>
                                    <select name="method" class="form-select" required>
                                        <option value="cash">{{ __('Cash') }}</option>
                                        <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                                        <option value="mobile_money">{{ __('Mobile Money') }}</option>
                                        <option value="other">{{ __('Other') }}</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Reference') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="text" name="reference" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Date Paid') }}</label>
                                    <input type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                </div>
                                <button type="submit" class="btn btn-gold w-100">{{ __('Record Payment') }}</button>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan
        </div>
    </div>
@endsection
