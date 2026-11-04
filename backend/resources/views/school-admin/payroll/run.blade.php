@extends('layouts.portal')

@section('title', __('Payroll').' '.$run->period.' — ElimuGrid')
@section('page-title', __('Payroll').' — '.$run->periodLabel())

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'payroll'])
@endsection

@php
    $cur = auth()->user()->school?->currency ?? 'TZS';
    $money = fn ($v) => number_format((float) $v, 2);
    $canManage = auth()->user()->can('payroll.manage');
    $canDisburse = auth()->user()->can('payroll.disburse');
    $methodLabel = fn ($m) => __(ucwords(str_replace('_', ' ', $m)));
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <a href="{{ route('school-admin.payroll.index') }}" class="d-inline-block mb-3 small"><i class="bi bi-arrow-left"></i> {{ __('Back to payroll') }}</a>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="badge rounded-pill px-3 py-2 {{ ['draft' => 'badge-status-pending', 'approved' => 'bg-info-subtle text-info-emphasis', 'paid' => 'badge-status-active'][$run->status] }}">{{ __(ucfirst($run->status)) }}</span>
                @if ($run->approved_at)
                    <span class="text-secondary small ms-2">{{ __('Approved by :name on :date', ['name' => $run->approver?->name ?? '—', 'date' => $run->approved_at->format('d M Y')]) }}</span>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($run->status !== 'draft' && $canManage)
                    <form method="POST" action="{{ route('school-admin.payroll.runs.email', $run) }}" onsubmit="return confirm('{{ __('E-mail every staff member their payslip (PDF)?') }}');">@csrf
                        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-envelope-paper me-1"></i> {{ __('E-mail payslips') }}</button></form>
                @endif
                @if ($run->status !== 'draft' && $canManage)
                    <form method="POST" action="{{ route('school-admin.payroll.runs.sms', $run) }}" onsubmit="return confirm('{{ __('Send every staff member an SMS with their net pay?') }}');">@csrf
                        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-chat-text me-1"></i> {{ __('SMS payslips') }}</button></form>
                @endif
                <a href="{{ route('school-admin.payroll.runs.export', $run) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i> {{ __('Payment sheet (CSV)') }}</a>
                @if ($run->isDraft() && $canManage)
                    <form method="POST" action="{{ route('school-admin.payroll.runs.regenerate', $run) }}">@csrf @method('PATCH')
                        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat me-1"></i> {{ __('Regenerate') }}</button></form>
                    <form method="POST" action="{{ route('school-admin.payroll.runs.approve', $run) }}" onsubmit="return confirm('{{ __('Approve this payroll? Amounts can no longer be changed.') }}');">@csrf @method('PATCH')
                        <button class="btn btn-gold btn-sm"><i class="bi bi-patch-check me-1"></i> {{ __('Approve') }}</button></form>
                    <form method="POST" action="{{ route('school-admin.payroll.runs.destroy', $run) }}" onsubmit="return confirm('{{ __('Delete this draft run?') }}');">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
                @endif
                @if ($run->status === 'approved' && $canDisburse && $totals['pending_count'] > 0)
                    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#payAllModal"><i class="bi bi-send-check me-1"></i> {{ __('Pay all pending') }} ({{ $totals['pending_count'] }})</button>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Gross pay') }}</div><div class="value fs-4">{{ $cur }} {{ $money($totals['gross']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Deductions') }}</div><div class="value fs-4 text-danger">{{ $cur }} {{ $money($totals['deductions']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Net pay') }}</div><div class="value fs-4">{{ $cur }} {{ $money($totals['net']) }}</div></div></div></div>
        @if ($employerCost > 0)
            <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Employer contributions') }}</div><div class="value fs-4">{{ $cur }} {{ $money($employerCost) }}</div></div></div></div>
        @endif
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Paid out') }}</div><div class="value fs-4 text-success">{{ $cur }} {{ $money($totals['paid']) }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header bg-white">{{ __('Payslips') }}</div>
        @if ($run->payslips->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No payslips — no active staff have a basic salary yet.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>{{ __('Employee') }}</th><th class="text-end">{{ __('Basic') }}</th><th class="text-end">{{ __('Allowances') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Net pay') }}</th><th>{{ __('Payment') }}</th><th class="text-end"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($run->payslips->sortBy(fn ($p) => $p->staffProfile->user->name) as $payslip)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $payslip->staffProfile->user->name }}</div>
                                    <div class="text-secondary small">{{ $payslip->staffProfile->employee_number }}@if ($payslip->staffProfile->bank_name) · {{ $payslip->staffProfile->bank_name }}@endif</div>
                                </td>
                                <td class="text-end">{{ $money($payslip->basic_salary) }}</td>
                                <td class="text-end text-success">{{ $money($payslip->total_allowances) }}</td>
                                <td class="text-end text-danger">{{ $money($payslip->total_deductions) }}</td>
                                <td class="text-end fw-bold">{{ $money($payslip->net_pay) }}</td>
                                <td>
                                    @if ($payslip->isPaid())
                                        <span class="badge badge-status-active rounded-pill px-2 py-1">{{ __('Paid') }}</span>
                                        <div class="text-secondary small">{{ $methodLabel($payslip->payment_method) }} · {{ $payslip->paid_at->format('d M') }}@if ($payslip->payment_reference) · {{ $payslip->payment_reference }}@endif</div>
                                    @else
                                        <span class="badge badge-status-pending rounded-pill px-2 py-1">{{ __('Pending') }}</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('school-admin.payroll.payslips.pdf', $payslip) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i></a>
                                    @if ($run->status === 'approved' && $canDisburse && ! $payslip->isPaid())
                                        <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#payModal{{ $payslip->id }}">{{ __('Pay') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>


    <div class="card mt-3">
        <div class="card-header bg-white">{{ __('One-off adjustments for :month', ['month' => $run->periodLabel()]) }}
            <span class="text-secondary small ms-2">{{ __('Arrears, back-pay, bonuses, advance recoveries') }}</span></div>
        @if ($adjustments->isNotEmpty())
            <div class="table-responsive">
                <table class="table mb-0 align-middle small">
                    <tbody>
                        @foreach ($adjustments as $adj)
                            <tr>
                                <td>{{ $adj->staffProfile?->user?->name }}</td>
                                <td>{{ $adj->name }}@if ($adj->note)<div class="text-secondary">{{ $adj->note }}</div>@endif</td>
                                <td><span class="badge {{ $adj->type === 'allowance' ? 'badge-status-active' : 'bg-danger-subtle text-danger-emphasis' }}">{{ $adj->type === 'allowance' ? __('Adds') : __('Deducts') }}</span></td>
                                <td class="text-end">{{ $cur }} {{ $money($adj->amount) }}</td>
                                <td class="text-end">
                                    @if ($run->isDraft() && $canManage)
                                        <form method="POST" action="{{ route('school-admin.payroll.adjustments.destroy', $adj) }}" onsubmit="return confirm('{{ __('Remove this adjustment?') }}');">@csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($run->isDraft() && $canManage)
            <form method="POST" action="{{ route('school-admin.payroll.adjustments.store') }}" class="card-body border-top">
                @csrf
                <input type="hidden" name="period" value="{{ $run->period }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label small">{{ __('Employee') }}</label>
                        <select name="staff_profile_id" class="form-select form-select-sm" required>@foreach ($staffOptions as $opt)<option value="{{ $opt->id }}">{{ $opt->user?->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label small">{{ __('Description') }}</label><input type="text" name="name" class="form-control form-control-sm" maxlength="100" placeholder="{{ __('Back-pay for March') }}" required></div>
                    <div class="col-md-2"><label class="form-label small">{{ __('Effect') }}</label>
                        <select name="type" class="form-select form-select-sm"><option value="allowance">{{ __('Adds to pay') }}</option><option value="deduction">{{ __('Deducts from pay') }}</option></select></div>
                    <div class="col-md-2"><label class="form-label small">{{ __('Amount') }}</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><button class="btn btn-gold btn-sm w-100">{{ __('Add') }}</button></div>
                </div>
                <div class="form-text">{{ __('Saved adjustments are applied when you Regenerate this draft.') }}</div>
            </form>
        @elseif ($adjustments->isEmpty())
            <div class="card-body text-secondary small">{{ __('None for this month.') }}</div>
        @endif
    </div>

    @if ($run->status === 'approved' && $canDisburse)
        @foreach ($run->payslips->where('payment_status', 'pending') as $payslip)
            <div class="modal fade" id="payModal{{ $payslip->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('school-admin.payroll.payslips.disburse', $payslip) }}">
                        @csrf
                        <div class="modal-header"><h2 class="modal-title h5">{{ __('Pay salary') }} — {{ $payslip->staffProfile->user->name }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <div class="alert alert-light border">{{ __('Net pay') }}: <strong>{{ $cur }} {{ $money($payslip->net_pay) }}</strong>
                                @if ($payslip->staffProfile->bank_name)<div class="small text-secondary">{{ $payslip->staffProfile->bank_name }} · {{ $payslip->staffProfile->bank_account_number }}</div>@endif</div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Payment method') }}</label>
                                <select name="payment_method" class="form-select" required>
                                    @foreach ($methods as $m)<option value="{{ $m }}">{{ $methodLabel($m) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="mb-1"><label class="form-label">{{ __('Reference') }} <span class="text-secondary">({{ __('optional') }})</span></label><input type="text" name="payment_reference" class="form-control" maxlength="100" placeholder="{{ __('Transaction / cheque no.') }}"></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-gold">{{ __('Mark as paid') }}</button></div>
                    </form></div>
            </div>
        @endforeach

        <div class="modal fade" id="payAllModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('school-admin.payroll.runs.disburse-all', $run) }}">
                    @csrf
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('Pay all pending salaries') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <p class="text-secondary small">{{ __('Marks every pending payslip in this run as paid using one method. Do this after the money has actually been sent.') }}</p>
                        <label class="form-label">{{ __('Payment method') }}</label>
                        <select name="payment_method" class="form-select" required>@foreach ($methods as $m)<option value="{{ $m }}">{{ $methodLabel($m) }}</option>@endforeach</select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-gold">{{ __('Pay all') }}</button></div>
                </form></div>
        </div>
    @endif
@endsection
