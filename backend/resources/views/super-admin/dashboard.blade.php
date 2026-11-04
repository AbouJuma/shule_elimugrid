@extends('layouts.portal')

@section('title', 'Super Admin — ElimuGrid')
@section('page-title', __('Platform'))

@section('sidebar')
    <a class="nav-link active" href="{{ route('super-admin.dashboard') }}">
        <i class="bi bi-speedometer2 me-2"></i> {{ __('Platform Dashboard') }}
    </a>
@endsection

@push('styles')
<style>
    .stat-icon { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .stat-card { height: 100%; }
    .bar-row { display: flex; align-items: center; gap: .6rem; margin-bottom: .5rem; font-size: .85rem; }
    .bar-row .lbl { width: 120px; flex: none; color: #5a6478; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-row .track { flex: 1; background: #eef1f6; border-radius: 6px; height: 14px; overflow: hidden; }
    .bar-row .fill { height: 100%; border-radius: 6px; background: #17376b; }
    .bar-row .val { width: 36px; flex: none; text-align: right; font-variant-numeric: tabular-nums; }
    .col-chart { display: flex; align-items: flex-end; gap: .6rem; height: 130px; }
    .col-chart .col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; font-size: .75rem; color: #5a6478; }
    .col-chart .bar { width: 100%; max-width: 46px; border-radius: 6px 6px 0 0; background: #d4a017; min-height: 3px; }
    .col-chart .num { font-size: .72rem; margin-bottom: 2px; color: #1a1f2b; }
</style>
@endpush

@php
    $tones = [
        'navy' => ['#e3e9f5', '#17376b'], 'green' => ['#dff5e8', '#1b7a4d'],
        'gold' => ['#fbf1d6', '#b8860b'], 'warning' => ['#fcebd6', '#c97a1a'], 'danger' => ['#fbe3e3', '#b3261e'],
    ];
    $s = $platform['stats'];
    $maxOnboarded = max(1, collect($platform['onboardedByMonth'])->max('value'));
    $maxPackage = max(1, collect($platform['schoolsByPackage'])->max('value') ?: 1);
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">{{ __('Every school on ElimuGrid') }}</h2>
            <div class="text-secondary">{{ __(':n schools · :a active', ['n' => $s['total_schools'], 'a' => $s['active_schools']]) }}</div>
        </div>
        @can('schools.create')
            <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#newSchoolModal">
                <i class="bi bi-plus-lg me-1"></i> {{ __('New School') }}
            </button>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        @php
            $cards = [
                ['label' => __('Total Schools'), 'value' => $s['total_schools'], 'icon' => 'bi-building', 'tone' => 'navy'],
                ['label' => __('Active'), 'value' => $s['active_schools'], 'icon' => 'bi-check-circle', 'tone' => 'green'],
                ['label' => __('Pending'), 'value' => $s['pending_schools'], 'icon' => 'bi-hourglass-split', 'tone' => 'warning'],
                ['label' => __('Suspended'), 'value' => $s['suspended_schools'], 'icon' => 'bi-slash-circle', 'tone' => 'danger'],
                ['label' => __('Active Students'), 'value' => number_format($s['total_students']), 'icon' => 'bi-people', 'tone' => 'gold'],
                ['label' => __('Staff Accounts'), 'value' => number_format($s['total_staff']), 'icon' => 'bi-person-badge', 'tone' => 'navy'],
            ];
        @endphp
        @foreach ($cards as $c)
            @php [$bg, $fg] = $tones[$c['tone']]; @endphp
            <div class="col-6 col-lg-2">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="stat-icon" style="background: {{ $bg }}; color: {{ $fg }};"><i class="bi {{ $c['icon'] }}"></i></span>
                        <div class="min-w-0">
                            <div class="text-secondary small text-truncate">{{ $c['label'] }}</div>
                            <div class="fs-4 fw-bold lh-1" style="color: #0a1f44;">{{ $c['value'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white">{{ __('Schools onboarded — last 6 months') }}</div>
                <div class="card-body">
                    <div class="col-chart">
                        @foreach ($platform['onboardedByMonth'] as $r)
                            <div class="col">
                                <div class="num">{{ $r['value'] }}</div>
                                <div class="bar" style="height: {{ max(3, $r['value'] / $maxOnboarded * 110) }}px;"></div>
                                <div class="mt-1">{{ $r['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white">{{ __('Schools by package') }}</div>
                <div class="card-body">
                    @forelse ($platform['schoolsByPackage'] as $row)
                        <div class="bar-row">
                            <div class="lbl">{{ $row['label'] }}</div>
                            <div class="track"><div class="fill" style="width: {{ max(4, $row['value'] / $maxPackage * 100) }}%;"></div></div>
                            <div class="val">{{ $row['value'] }}</div>
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">{{ __('No schools yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search schools…') }}" value="{{ request('search') }}" style="max-width: 220px;">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="pending" @selected(request('status')==='pending')>{{ __('Pending') }}</option>
                    <option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option>
                    <option value="suspended" @selected(request('status')==='suspended')>{{ __('Suspended') }}</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
        </div>

        @if ($schools->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-building display-6 d-block mb-2"></i>
                {{ __('No schools match these filters.') }}
            </div>
        @else
            <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('School') }}</th>
                        <th>{{ __('Package') }}</th>
                        <th>{{ __('Students') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schools as $school)
                        <tr>
                            <td class="text-secondary">{{ $schools->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="fw-semibold">{{ $school->name }}</div>
                                <div class="text-secondary small">{{ $school->slug }}</div>
                            </td>
                            <td>{{ $school->subscriptionPackage?->name ?? '—' }}</td>
                            <td>{{ number_format($school->students_count) }}</td>
                            <td>
                                <span class="badge badge-status-{{ $school->status }} rounded-pill px-3 py-2">
                                    {{ ucfirst($school->status) }}
                                </span>
                            </td>
                            <td class="text-secondary">{{ $school->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                @can('schools.suspend')
                                    @if ($school->status !== 'suspended')
                                        <form method="POST" action="{{ route('super-admin.schools.suspend', $school) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('{{ __('Suspend :p1? Nobody there will be able to sign in.') }}'.replace(':p1', {{ Illuminate\Support\Js::from($school->name) }}));">
                                                <i class="bi bi-slash-circle"></i> {{ __('Suspend') }}
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                                @can('schools.activate')
                                    @if ($school->status !== 'active')
                                        <form method="POST" action="{{ route('super-admin.schools.activate', $school) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-check-circle"></i> {{ __('Activate') }}
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            @include('school-admin.partials.table-pagination', ['paginator' => $schools])
        @endif
    </div>

    <div class="modal fade" id="newSchoolModal" tabindex="-1" aria-labelledby="newSchoolModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('super-admin.schools.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="newSchoolModalLabel">{{ __('Create School') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0 small">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ __($error) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <h3 class="h6 text-secondary text-uppercase mb-3">{{ __('School details') }}</h3>
                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="form-label">{{ __('School name') }}</label>
                                <input type="text" name="school[name]" class="form-control" value="{{ old('school.name') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Registration No.') }}</label>
                                <input type="text" name="school[registration_number]" class="form-control" value="{{ old('school.registration_number') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('School email') }}</label>
                                <input type="email" name="school[email]" class="form-control" value="{{ old('school.email') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('School phone') }}</label>
                                <input type="text" name="school[phone]" class="form-control" value="{{ old('school.phone') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Address') }}</label>
                                <textarea name="school[address]" class="form-control" rows="2">{{ old('school.address') }}</textarea>
                            </div>
                        </div>

                        <h3 class="h6 text-secondary text-uppercase mb-3">{{ __('First School Administrator') }}</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Full name') }}</label>
                                <input type="text" name="admin[name]" class="form-control" value="{{ old('admin.name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="admin[email]" class="form-control" value="{{ old('admin.email') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" name="admin[phone]" class="form-control" value="{{ old('admin.phone') }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-gold">{{ __('Create School') }}</button>
                    </div>
                </form>
        </div>
    </div>

    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new bootstrap.Modal(document.getElementById('newSchoolModal')).show();
            });
        </script>
    @endif

    @if (session('onboarded'))
        @php $o = session('onboarded'); @endphp
        <div class="modal fade" id="onboardedRevealModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __(':p1 is ready', ['p1' => $o['school']]) }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">{{ __('School Administrator login') }}: {{ $o['admin_email'] }}</p>
                        <label class="form-label small text-secondary">{{ __('Temporary password') }}</label>
                        <input type="text" class="form-control fw-bold" value="{{ $o['temporary_password'] }}" readonly onclick="this.select();">
                        <div class="form-text">{{ __('It is shown only now — send it to them securely; they should change it after signing in.') }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-gold" data-bs-dismiss="modal">{{ __('Done') }}</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new bootstrap.Modal(document.getElementById('onboardedRevealModal')).show();
            });
        </script>
    @endif
@endsection
