@extends('layouts.portal')

@section('title', __('Payroll').' — ElimuGrid')
@section('page-title', __('Payroll'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'payroll'])
@endsection

@php
    $cur = auth()->user()->school?->currency ?? 'TZS';
    $money = fn ($v) => number_format((float) $v, 2);
    $canManage = auth()->user()->can('payroll.manage');
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card card-metric"><div class="card-body">
            <div class="text-secondary small"><i class="bi bi-people me-1"></i> {{ __('Staff on payroll') }}</div>
            <div class="value">{{ $stats['staff_count'] }}</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card card-metric"><div class="card-body">
            <div class="text-secondary small"><i class="bi bi-cash-stack me-1"></i> {{ __('Monthly basic salary bill') }}</div>
            <div class="value fs-4">{{ $cur }} {{ $money($stats['monthly_bill']) }}</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card card-metric"><div class="card-body">
            <div class="text-secondary small"><i class="bi bi-hourglass-split me-1"></i> {{ __('Awaiting disbursement') }}</div>
            <div class="value fs-4 text-warning-emphasis">{{ $cur }} {{ $money($stats['unpaid']) }}</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card card-metric"><div class="card-body">
            <div class="text-secondary small"><i class="bi bi-check2-circle me-1"></i> {{ __('Paid this year') }}</div>
            <div class="value fs-4 text-success">{{ $cur }} {{ $money($stats['paid_this_year']) }}</div></div></div></div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'runs' ? 'active' : '' }}" href="{{ route('school-admin.payroll.index') }}">{{ __('Payroll runs') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'salaries' ? 'active' : '' }}" href="{{ route('school-admin.payroll.index', ['tab' => 'salaries']) }}">{{ __('Staff salaries') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'components' ? 'active' : '' }}" href="{{ route('school-admin.payroll.index', ['tab' => 'components']) }}">{{ __('Allowances & deductions') }}</a></li>
    </ul>

    {{-- ============ RUNS ============ --}}
    @if ($tab === 'runs')
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>{{ __('Monthly payroll runs') }}</span>
                @if ($canManage)
                    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newRunModal"><i class="bi bi-plus-lg me-1"></i> {{ __('New Payroll Run') }}</button>
                @endif
            </div>
            @if ($runs->isEmpty())
                <div class="card-body text-center text-secondary py-5">
                    <i class="bi bi-wallet2 display-6 d-block mb-2"></i>
                    {{ __('No payroll runs yet. Set up staff salaries, then start a run for the month.') }}
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Period') }}</th><th>{{ __('Staff') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Net pay') }}</th><th style="min-width:140px;">{{ __('Disbursed') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($runs as $run)
                                @php $pct = $run->net_total > 0 ? round($run->paid_total / $run->net_total * 100) : 0; @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $run->periodLabel() }}</td>
                                    <td>{{ $run->payslips_count }}</td>
                                    <td class="text-end">{{ $money($run->gross_total) }}</td>
                                    <td class="text-end text-danger">{{ $money($run->deductions_total) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($run->net_total) }}</td>
                                    <td>
                                        <div class="progress" style="height: 7px;"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
                                        <div class="small text-secondary">{{ $pct }}%</div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill px-2 py-1 {{ ['draft' => 'badge-status-pending', 'approved' => 'bg-info-subtle text-info-emphasis', 'paid' => 'badge-status-active'][$run->status] }}">{{ __(ucfirst($run->status)) }}</span>
                                    </td>
                                    <td class="text-end"><a href="{{ route('school-admin.payroll.runs.show', $run) }}" class="btn btn-outline-secondary btn-sm">{{ __('Open') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('school-admin.partials.table-pagination', ['paginator' => $runs])
            @endif
        </div>
    @endif

    {{-- ============ SALARIES ============ --}}
    @if ($tab === 'salaries')
        <div class="card">
            <div class="card-header bg-white">{{ __('Staff salaries') }} <span class="text-secondary small ms-1">— {{ __('a basic salary is required for staff to appear on payslips') }}</span></div>
            @if ($staff->isEmpty())
                <div class="card-body text-center text-secondary py-5">{{ __('No active staff yet.') }} <a href="{{ route('school-admin.staff.index') }}">{{ __('Add staff') }}</a></div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>{{ __('Employee') }}</th><th>{{ __('Job title') }}</th><th class="text-end">{{ __('Basic salary') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Extras') }}</th><th class="text-end"></th></tr></thead>
                        <tbody>
                            @foreach ($staff as $profile)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $profile->user->name }}</div>
                                        <div class="text-secondary small">{{ $profile->employee_number }}</div>
                                    </td>
                                    <td class="text-secondary small">{{ $profile->job_title ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($profile->basic_salary > 0) {{ $money($profile->basic_salary) }}
                                        @else <span class="badge badge-status-pending rounded-pill">{{ __('Not set') }}</span> @endif
                                    </td>
                                    <td class="text-secondary small">{{ $profile->bank_name ? $profile->bank_name.' · '.$profile->bank_account_number : '—' }}</td>
                                    <td class="text-secondary small">{{ $profile->salaryComponents->count() ?: '—' }}</td>
                                    <td class="text-end">
                                        @if ($canManage)
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#salaryModal{{ $profile->id }}"><i class="bi bi-pencil"></i> {{ __('Edit') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- ============ COMPONENTS ============ --}}
    @if ($tab === 'components')
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>{{ __('Allowances & deductions') }}</span>
                @if ($canManage)
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('school-admin.payroll.components.statutory') }}" onsubmit="return confirm('{{ __('Add Tanzania statutory components (PAYE, NSSF, WCF, SDL)? They are ordinary rows you can edit — verify the rates first.') }}');">@csrf
                            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-bank me-1"></i> {{ __('Add Tanzania statutory set') }}</button></form>
                        <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newComponentModal"><i class="bi bi-plus-lg me-1"></i> {{ __('Add Component') }}</button>
                    </div>
                @endif
            </div>
            @if ($components->isEmpty())
                <div class="card-body text-center text-secondary py-5">
                    {{ __('Add allowances (housing, transport…) and deductions (pension, tax, loans…). Mark one "applies to everyone" to include it automatically.') }}
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Value') }}</th><th>{{ __('Applies to') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($components as $component)
                                <tr>
                                    <td class="fw-semibold">{{ $component->name }}</td>
                                    <td><span class="badge rounded-pill px-2 py-1 {{ $component->isAllowance() ? 'badge-status-active' : 'bg-danger-subtle text-danger-emphasis' }}">{{ __(ucfirst($component->type)) }}</span></td>
                                    <td>
                                        @if ($component->hasBands())
                                            {{ __('Tax bands') }}: @foreach ($component->bands as $b){{ $b['upto'] === null ? __('above') : number_format($b['upto']) }}={{ $b['rate'] }}%@if (! $loop->last) · @endif @endforeach
                                        @else
                                            {{ $component->calculation === 'percent' ? rtrim(rtrim(number_format($component->default_amount, 2), '0'), '.').'% '.__('of '.$component->base) : $cur.' '.$money($component->default_amount) }}
                                        @endif
                                        @if ($component->pre_tax)<span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">{{ __('before tax') }}</span>@endif
                                        @if ($component->employer_paid)<span class="badge bg-info-subtle text-info-emphasis ms-1">{{ __('paid by school') }}</span>@endif
                                    </td>
                                    <td class="text-secondary small">{{ $component->apply_to_all ? __('Everyone') : __('Assigned staff only') }}</td>
                                    <td class="text-end">
                                        @if ($canManage)
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editComponentModal{{ $component->id }}"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" action="{{ route('school-admin.payroll.components.destroy', $component) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this component? Past payslips are not affected.') }}');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- ============ MODALS ============ --}}
    @if ($canManage)
        <div class="modal fade" id="newRunModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('school-admin.payroll.runs.store') }}">
                    @csrf
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('New Payroll Run') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <label class="form-label">{{ __('Month') }}</label>
                        <input type="month" name="period" class="form-control" value="{{ $defaultPeriod }}" required>
                        <div class="form-text">{{ __('Payslips are built from each active staff member\'s salary set-up, and can be regenerated until the run is approved.') }}</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-gold">{{ __('Create Run') }}</button></div>
                </form></div>
        </div>

        @foreach ([null, ...$components->all()] as $component)
            <div class="modal fade" id="{{ $component ? 'editComponentModal'.$component->id : 'newComponentModal' }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ $component ? route('school-admin.payroll.components.update', $component) : route('school-admin.payroll.components.store') }}">
                        @csrf @if ($component) @method('PUT') @endif
                        <div class="modal-header"><h2 class="modal-title h5">{{ $component ? __('Edit Component') : __('Add Component') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">{{ __('Name') }}</label><input type="text" name="name" class="form-control" value="{{ $component?->name }}" placeholder="{{ __('Housing allowance') }}" required></div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Type') }}</label>
                                    <select name="type" class="form-select" required>
                                        <option value="allowance" @selected($component?->type === 'allowance')>{{ __('Allowance (adds to pay)') }}</option>
                                        <option value="deduction" @selected($component?->type === 'deduction')>{{ __('Deduction (comes off pay)') }}</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Calculated as') }}</label>
                                    <select name="calculation" class="form-select" required>
                                        <option value="fixed" @selected($component?->calculation === 'fixed')>{{ __('Fixed amount') }}</option>
                                        <option value="percent" @selected($component?->calculation === 'percent')>{{ __('Percentage') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Percentage of') }}</label>
                                <select name="base" class="form-select">
                                    <option value="basic" @selected(($component?->base ?? 'basic') === 'basic')>{{ __('Basic salary') }}</option>
                                    <option value="gross" @selected($component?->base === 'gross')>{{ __('Gross pay') }}</option>
                                    <option value="taxable" @selected($component?->base === 'taxable')>{{ __('Taxable income (gross minus pre-tax deductions)') }}</option>
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label">{{ __('Default value') }}</label><input type="number" step="0.01" min="0" name="default_amount" class="form-control" value="{{ $component?->default_amount ?? 0 }}" required></div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Tax bands') }} <span class="text-secondary">({{ __('optional — deductions only') }})</span></label>
                                <textarea name="bands_text" rows="4" class="form-control font-monospace small" placeholder="270000=0&#10;520000=8&#10;760000=20&#10;1000000=25&#10;above=30">@if ($component?->hasBands()){{ collect($component->bands)->map(fn ($b) => ($b['upto'] === null ? 'above' : (int) $b['upto']).'='.$b['rate'])->implode("\n") }}@endif</textarea>
                                <div class="form-text">{{ __('One line per band: income up to that limit is taxed at that % (progressive). The last line, above=30, covers everything over. Replaces the value above.') }}</div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pre_tax" value="1" id="pt{{ $component?->id ?? 'new' }}" @checked($component?->pre_tax)>
                                <label class="form-check-label" for="pt{{ $component?->id ?? 'new' }}">{{ __('Deducted before tax (e.g. pension) — lowers taxable income') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="employer_paid" value="1" id="ep{{ $component?->id ?? 'new' }}" @checked($component?->employer_paid)>
                                <label class="form-check-label" for="ep{{ $component?->id ?? 'new' }}">{{ __('Paid by the school on top of salary (not taken from the employee)') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="apply_to_all" value="1" id="ata{{ $component?->id ?? 'new' }}" @checked($component?->apply_to_all)>
                                <label class="form-check-label" for="ata{{ $component?->id ?? 'new' }}">{{ __('Applies to everyone automatically') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button></div>
                    </form></div>
            </div>
        @endforeach

        @foreach ($staff as $profile)
            @php $overrides = $profile->salaryComponents->pluck('amount', 'payroll_component_id'); @endphp
            <div class="modal fade" id="salaryModal{{ $profile->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" method="POST" action="{{ route('school-admin.payroll.staff.salary', $profile) }}">
                        @csrf @method('PUT')
                        <div class="modal-header"><h2 class="modal-title h5">{{ __('Salary') }} — {{ $profile->user->name }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4"><label class="form-label">{{ __('Basic salary') }} ({{ $cur }})</label><input type="number" step="0.01" min="0" name="basic_salary" class="form-control" value="{{ $profile->basic_salary }}" required></div>
                                <div class="col-md-4"><label class="form-label">{{ __('Bank') }}</label><input type="text" name="bank_name" class="form-control" value="{{ $profile->bank_name }}"></div>
                                <div class="col-md-4"><label class="form-label">{{ __('Account number') }}</label><input type="text" name="bank_account_number" class="form-control" value="{{ $profile->bank_account_number }}"></div>
                            </div>
                            @if ($components->isNotEmpty())
                                <h3 class="h6 text-secondary text-uppercase">{{ __('Allowances & deductions') }}</h3>
                                <p class="small text-secondary">{{ __('Leave blank to use the default (or none). Enter a value to override, or 0 to exclude a component that applies to everyone.') }}</p>
                                @foreach ($components as $component)
                                    <div class="row g-2 align-items-center mb-2">
                                        <div class="col-7">
                                            {{ $component->name }}
                                            <span class="badge {{ $component->isAllowance() ? 'badge-status-active' : 'bg-danger-subtle text-danger-emphasis' }} rounded-pill">{{ __(ucfirst($component->type)) }}</span>
                                            <div class="text-secondary small">{{ __('Default') }}: {{ $component->calculation === 'percent' ? rtrim(rtrim(number_format($component->default_amount, 2), '0'), '.').'%' : $money($component->default_amount) }}@if ($component->apply_to_all) · {{ __('everyone') }}@endif</div>
                                        </div>
                                        <div class="col-5">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="components[{{ $component->id }}]"
                                                   value="{{ $overrides[$component->id] ?? '' }}" placeholder="{{ $component->calculation === 'percent' ? '%' : $cur }}">
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button></div>
                    </form></div>
            </div>
        @endforeach
    @endif
@endsection
