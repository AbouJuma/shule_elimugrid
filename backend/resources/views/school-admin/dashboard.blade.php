@extends('layouts.portal')

@section('title', __('Dashboard').' — ElimuGrid')
@section('page-title', __('Dashboard'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'dashboard'])
@endsection

@php
    $tones = [
        'navy' => ['#e3e9f5', '#17376b'], 'green' => ['#dff5e8', '#1b7a4d'],
        'gold' => ['#fbf1d6', '#b8860b'], 'warning' => ['#fcebd6', '#c97a1a'],
    ];
    $lists = $d['lists'];
    $charts = $d['charts'];
    $money = fn ($v) => number_format((float) $v);
@endphp

@push('styles')
<style>
    .stat-icon { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .stat-card { transition: transform .12s ease, box-shadow .12s ease; text-decoration: none; color: inherit; height: 100%; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(10,31,68,.08); color: inherit; }
    .bar-row { display: flex; align-items: center; gap: .6rem; margin-bottom: .45rem; font-size: .85rem; }
    .bar-row .lbl { width: 92px; flex: none; color: #5a6478; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-row .track { flex: 1; background: #eef1f6; border-radius: 6px; height: 14px; overflow: hidden; }
    .bar-row .fill { height: 100%; border-radius: 6px; background: #17376b; }
    .bar-row .val { width: 64px; flex: none; text-align: right; font-variant-numeric: tabular-nums; }
    .col-chart { display: flex; align-items: flex-end; gap: .6rem; height: 150px; }
    .col-chart .col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; font-size: .75rem; color: #5a6478; }
    .col-chart .bar { width: 100%; max-width: 46px; border-radius: 6px 6px 0 0; background: #d4a017; min-height: 3px; }
    .col-chart .num { font-size: .72rem; margin-bottom: 2px; color: #1a1f2b; }
</style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">{{ __('Welcome, :name', ['name' => $user->name]) }}</h2>
            <div class="text-secondary">{{ $user->school?->name }} · {{ $d['greeting'] }}</div>
        </div>
        @if ($d['actions'])
            <div class="d-flex flex-wrap gap-2">
                @foreach ($d['actions'] as $a)
                    <a href="{{ $a['href'] }}" class="btn btn-outline-secondary btn-sm"><i class="bi {{ $a['icon'] }} me-1"></i>{{ $a['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>

    @if ($d['stats'])
        <div class="row g-3 mb-3">
            @foreach ($d['stats'] as $s)
                @php [$bg, $fg] = $tones[$s['tone']] ?? $tones['navy']; @endphp
                <div class="col-6 col-lg-3">
                    <a href="{{ $s['href'] }}" class="card stat-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="stat-icon" style="background: {{ $bg }}; color: {{ $fg }};"><i class="bi {{ $s['icon'] }}"></i></span>
                            <div class="min-w-0">
                                <div class="text-secondary small text-truncate">{{ $s['label'] }}</div>
                                <div class="fs-4 fw-bold lh-1" style="color: #0a1f44;">{{ $s['value'] }}</div>
                                <div class="small text-secondary text-truncate">{{ $s['sub'] }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-light border">{{ __('Your role has no dashboard widgets yet. Use the menu on the left to open the modules you have access to.') }}</div>
    @endif

    <div class="row g-3 mb-3">
        @isset($charts['attendance'])
            <div class="col-lg-4">
                <div class="card h-100"><div class="card-header bg-white">{{ $charts['attendance']['title'] }}</div>
                    <div class="card-body">
                        <div class="col-chart">
                            @foreach ($charts['attendance']['rows'] as $r)
                                <div class="col">
                                    <div class="num">{{ $r['value'] === null ? '–' : $r['value'].'%' }}</div>
                                    <div class="bar" style="height: {{ $r['value'] === null ? 3 : max(3, $r['value'] * 1.15) }}px; {{ $r['value'] === null ? 'background:#e2e5ea' : '' }}"></div>
                                    <div class="mt-1">{{ $r['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        @isset($charts['fees'])
            @php $maxFee = max(1, collect($charts['fees']['rows'])->max('value')); @endphp
            <div class="col-lg-4">
                <div class="card h-100"><div class="card-header bg-white">{{ $charts['fees']['title'] }}</div>
                    <div class="card-body">
                        <div class="col-chart">
                            @foreach ($charts['fees']['rows'] as $r)
                                <div class="col">
                                    <div class="num">{{ $r['value'] >= 1000 ? number_format($r['value'] / 1000).'k' : number_format($r['value']) }}</div>
                                    <div class="bar" style="height: {{ max(3, $r['value'] / $maxFee * 115) }}px; background: #1b7a4d;"></div>
                                    <div class="mt-1">{{ $r['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        @isset($charts['byClass'])
            @php $maxClass = max(1, collect($charts['byClass']['rows'])->max('value')); @endphp
            <div class="col-lg-4">
                <div class="card h-100"><div class="card-header bg-white">{{ $charts['byClass']['title'] }}</div>
                    <div class="card-body">
                        @forelse ($charts['byClass']['rows'] as $r)
                            <div class="bar-row"><span class="lbl" title="{{ $r['label'] }}">{{ $r['label'] }}</span><span class="track"><span class="fill d-block" style="width: {{ $r['value'] / $maxClass * 100 }}%"></span></span><span class="val">{{ $r['value'] }}</span></div>
                        @empty
                            <div class="text-secondary small">{{ __('No students admitted yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endisset
    </div>

    <div class="row g-3">
        @isset($lists['myLessons'])
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-header bg-white">{{ __('My lessons today') }}</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($lists['myLessons'] as $e)
                            <li class="list-group-item d-flex justify-content-between">
                                <span><strong>{{ $e->subject->name }}</strong> · {{ $e->schoolClass->name }}@if ($e->stream) {{ $e->stream->name }}@endif @if ($e->room) <span class="text-secondary">· {{ $e->room }}</span>@endif</span>
                                <span class="text-secondary">{{ $e->period?->timeLabel() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endisset

        @isset($lists['trips'])
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-header bg-white d-flex justify-content-between"><span>{{ __('Buses on the road') }}</span><a href="{{ route('school-admin.transport-live') }}" class="small">{{ __('Open live map') }} →</a></div>
                    @if ($lists['trips']->isEmpty())
                        <div class="card-body text-secondary small">{{ __('No trip is running right now.') }}</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($lists['trips'] as $t)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><strong>{{ $t->vehicle->registration_number }}</strong> · {{ $t->route->name }}<div class="small text-secondary">{{ $t->vehicle->driver?->name }} · {{ __($t->direction === 'morning' ? 'Morning pick-up' : 'Afternoon drop-off') }}</div></span>
                                    <span class="badge badge-status-active rounded-pill">{{ __('since :time', ['time' => $t->started_at->format('H:i')]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset

        @isset($lists['leave'])
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-header bg-white d-flex justify-content-between"><span>{{ __('Leave awaiting approval') }}</span><a href="{{ route('school-admin.leave.index') }}" class="small">{{ __('Review') }} →</a></div>
                    @if ($lists['leave']->isEmpty())
                        <div class="card-body text-secondary small">{{ __('Nothing waiting for you.') }}</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($lists['leave'] as $l)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><strong>{{ $l->user?->name }}</strong> · {{ $l->leaveType?->name }}</span>
                                    <span class="text-secondary small">{{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M') }} ({{ $l->days }})</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset

        @isset($lists['recentPayments'])
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-header bg-white">{{ __('Recent payments') }}</div>
                    @if ($lists['recentPayments']->isEmpty())
                        <div class="card-body text-secondary small">{{ __('No payments recorded yet.') }}</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($lists['recentPayments'] as $p)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ $p->student?->fullName() }}<div class="small text-secondary">{{ $p->receipt_number }} · {{ $p->paid_on->format('d M') }}</div></span>
                                    <strong class="text-success">{{ $money($p->amount) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset

        @isset($lists['exams'])
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-header bg-white d-flex justify-content-between"><span>{{ __('Upcoming exams') }}</span><a href="{{ route('school-admin.exams.index') }}" class="small">{{ __('All exams') }} →</a></div>
                    @if ($lists['exams']->isEmpty())
                        <div class="card-body text-secondary small">{{ __('No exams scheduled.') }}</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($lists['exams'] as $e)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><strong>{{ $e->name }}</strong> · {{ $e->classes->pluck('name')->join(', ') }}</span>
                                    <span class="text-secondary small">{{ $e->start_date ? $e->start_date->format('d M') : __('Date not set') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset

        @isset($lists['recentAdmissions'])
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-header bg-white d-flex justify-content-between"><span>{{ __('Recent admissions') }}</span><a href="{{ route('school-admin.students.index') }}" class="small">{{ __('All students') }} →</a></div>
                    @if ($lists['recentAdmissions']->isEmpty())
                        <div class="card-body text-secondary small">{{ __('No students admitted yet.') }}</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($lists['recentAdmissions'] as $s)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ $s->fullName() }}<div class="small text-secondary">{{ $s->admission_number }}</div></span>
                                    <span class="text-secondary small">{{ $s->schoolClass?->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset
    </div>
@endsection
