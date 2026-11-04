<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1a1f2b; margin: 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; }
        .school-name { font-size: 17px; font-weight: bold; color: #0a1f44; text-transform: uppercase; }
        .meta { font-size: 8.5px; color: #5a6478; margin-top: 2px; }
        .banner { background: #0a1f44; color: #fff; padding: 8px 12px; margin-top: 12px; border-bottom: 3px solid #d4a017; }
        .banner .t { font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .banner .s { font-size: 9px; color: #e8b923; margin-top: 2px; }
        .info td { padding: 4px 6px; font-size: 10px; }
        .info .l { color: #5a6478; width: 18%; }
        .info .v { font-weight: bold; color: #0a1f44; width: 32%; border-bottom: 1px dotted #b6bccb; }
        .lines { margin-top: 14px; }
        .lines th { background: #e3e9f5; color: #0a1f44; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; border: 1px solid #c9d1e3; }
        .lines td { padding: 6px 8px; border: 1px solid #e2e5ea; }
        .lines .r { text-align: right; }
        .lines .total td { background: #f7f8fa; font-weight: bold; }
        .net { margin-top: 14px; }
        .net td { padding: 10px 12px; }
        .net .label { background: #0a1f44; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 55%; }
        .net .amount { background: #fbf1d6; border: 1px solid #d4a017; text-align: right; font-size: 17px; font-weight: bold; color: #0a1f44; }
        .paid { margin-top: 10px; font-size: 9.5px; color: #1b7a4d; }
        .sign { margin-top: 34px; }
        .sign td { width: 50%; text-align: center; font-size: 8.5px; color: #5a6478; padding: 0 26px; }
        .sign .line { border-top: 1px solid #1a1f2b; padding-top: 3px; }
        .footer { margin-top: 16px; text-align: center; font-size: 8px; color: #8a93a6; border-top: 1px solid #e2e5ea; padding-top: 5px; }
    </style>
</head>
<body>
@php
    $profile = $payslip->staffProfile;
    $cur = $school->currency ?? 'TZS';
    $money = fn ($v) => number_format((float) $v, 2);
    $logo = $school->logo_path && file_exists(storage_path('app/public/'.$school->logo_path)) ? storage_path('app/public/'.$school->logo_path) : null;
    $allowances = $payslip->lines->where('type', 'allowance');
    $deductions = $payslip->lines->where('type', 'deduction')->where('is_employer', false);
    $employerLines = $payslip->lines->where('is_employer', true);
@endphp

<table>
    <tr>
        <td style="width: 64px;">@if ($logo)<img src="{{ $logo }}" style="height: 54px;">@endif</td>
        <td style="text-align: center;">
            <div class="school-name">{{ $school->name }}</div>
            <div class="meta">{{ collect([$school->address, $school->phone, $school->email])->filter()->join('  |  ') }}</div>
        </td>
        <td style="width: 64px;"></td>
    </tr>
</table>

<div class="banner">
    <div class="t">{{ __('Payslip') }}</div>
    <div class="s">{{ $payslip->run->periodLabel() }}</div>
</div>

<table class="info" style="margin-top: 10px;">
    <tr><td class="l">{{ __('Employee') }}</td><td class="v">{{ $profile->user->name }}</td><td class="l">{{ __('Employee No.') }}</td><td class="v">{{ $profile->employee_number }}</td></tr>
    <tr><td class="l">{{ __('Job title') }}</td><td class="v">{{ $profile->job_title ?? '—' }}</td><td class="l">{{ __('Department') }}</td><td class="v">{{ $profile->department ?? '—' }}</td></tr>
    <tr><td class="l">{{ __('Bank') }}</td><td class="v">{{ $profile->bank_name ?? '—' }}</td><td class="l">{{ __('Account No.') }}</td><td class="v">{{ $profile->bank_account_number ?? '—' }}</td></tr>
</table>

<table class="lines">
    <thead><tr><th style="width: 65%;">{{ __('Earnings') }}</th><th class="r">{{ $cur }}</th></tr></thead>
    <tbody>
        <tr><td>{{ __('Basic salary') }}</td><td class="r">{{ $money($payslip->basic_salary) }}</td></tr>
        @foreach ($allowances as $line)<tr><td>{{ $line->name }}</td><td class="r">{{ $money($line->amount) }}</td></tr>@endforeach
        <tr class="total"><td>{{ __('Gross pay') }}</td><td class="r">{{ $money($payslip->gross_pay) }}</td></tr>
    </tbody>
</table>

<table class="lines" style="margin-top: 10px;">
    <thead><tr><th style="width: 65%;">{{ __('Deductions') }}</th><th class="r">{{ $cur }}</th></tr></thead>
    <tbody>
        @forelse ($deductions as $line)<tr><td>{{ $line->name }}</td><td class="r">{{ $money($line->amount) }}</td></tr>
        @empty<tr><td colspan="2" style="color: #8a93a6;">{{ __('None') }}</td></tr>@endforelse
        <tr class="total"><td>{{ __('Total deductions') }}</td><td class="r">{{ $money($payslip->total_deductions) }}</td></tr>
    </tbody>
</table>

<table class="net">
    <tr><td class="label">{{ __('Net pay') }}</td><td class="amount">{{ $cur }} {{ $money($payslip->net_pay) }}</td></tr>
</table>

@if ($employerLines->isNotEmpty())
    <table class="lines" style="margin-top: 10px;">
        <thead><tr><th style="width: 65%;">{{ __('Employer contributions (not deducted from your pay)') }}</th><th class="r">{{ $cur }}</th></tr></thead>
        <tbody>
            @foreach ($employerLines as $line)<tr><td>{{ $line->name }}</td><td class="r">{{ $money($line->amount) }}</td></tr>@endforeach
        </tbody>
    </table>
@endif

@if ($payslip->isPaid())
    <div class="paid">
        {{ __('Paid on :date via :method', ['date' => $payslip->paid_at->format('d M Y'), 'method' => __(ucwords(str_replace('_', ' ', $payslip->payment_method)))]) }}
        @if ($payslip->payment_reference) — {{ $payslip->payment_reference }}@endif
    </div>
@endif

<table class="sign">
    <tr><td><div class="line">{{ __('Employer') }}</div></td><td><div class="line">{{ __('Employee') }}</div></td></tr>
</table>

<div class="footer">{{ __('Generated on :date', ['date' => now()->format('d M Y')]) }} · {{ $school->name }}</div>
</body>
</html>
