@extends('layouts.portal')

@section('title', 'Reports — ElimuGrid')
@section('page-title', __('Reports'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'reports'])
@endsection

@section('content')
    <div class="row g-3">
        @php
            $cards = [
                ['route' => 'school-admin.reports.enrollment', 'icon' => 'people', 'title' => 'Enrollment', 'desc' => 'Student counts by class, gender, and status.'],
                ['route' => 'school-admin.reports.financial', 'icon' => 'cash-coin', 'title' => 'Financial', 'desc' => 'Invoiced, collected, and outstanding balances by class.'],
                ['route' => 'school-admin.reports.attendance', 'icon' => 'calendar-check', 'title' => 'Attendance', 'desc' => 'Attendance rate by class over a date range.'],
                ['route' => 'school-admin.reports.academic', 'icon' => 'clipboard-check', 'title' => 'Academic', 'desc' => 'Per-subject averages and pass rates for an exam.'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route($card['route']) }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body">
                            <i class="bi bi-{{ $card['icon'] }} display-6 text-gold"></i>
                            <h2 class="h6 mt-3 mb-1 text-dark">{{ $card['title'] }}</h2>
                            <p class="text-secondary small mb-0">{{ $card['desc'] }}</p>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
