@extends('layouts.portal')

@section('title', 'Enrollment Report — ElimuGrid')
@section('page-title', __('Enrollment Report'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'reports'])
@endsection

@section('content')
    <a href="{{ route('school-admin.reports.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Reports') }}
    </a>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span>{{ __('Students by class') }}</span>
            <a href="{{ route('school-admin.reports.enrollment', ['export' => 'csv']) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> {{ __('Export CSV') }}
            </a>
        </div>
        @if ($rows->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No classes have been set up yet.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Class') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Male') }}</th>
                        <th>{{ __('Female') }}</th>
                        <th>{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-secondary">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $row['class'] }}</td>
                            <td>{{ $row['total'] }}</td>
                            <td>{{ $row['male'] }}</td>
                            <td>{{ $row['female'] }}</td>
                            <td>{{ $row['active'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="fw-semibold border-top">
                        <td></td>
                        <td>{{ __('Total') }}</td>
                        <td>{{ $rows->sum('total') }}</td>
                        <td>{{ $rows->sum('male') }}</td>
                        <td>{{ $rows->sum('female') }}</td>
                        <td>{{ $rows->sum('active') }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
@endsection
