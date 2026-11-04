@extends('layouts.portal')

@section('title', 'Attendance Report — ElimuGrid')
@section('page-title', __('Attendance Report'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'reports'])
@endsection

@section('content')
    <a href="{{ route('school-admin.reports.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Reports') }}
    </a>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
                <span class="text-secondary small">{{ __('to') }}</span>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Filter') }}</button>
            </form>
            <a href="{{ route('school-admin.reports.attendance', ['export' => 'csv', 'from' => $from, 'to' => $to]) }}" class="btn btn-outline-secondary btn-sm">
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
                        <th>{{ __('Present') }}</th>
                        <th>{{ __('Absent') }}</th>
                        <th>{{ __('Late') }}</th>
                        <th>{{ __('Excused') }}</th>
                        <th>{{ __('Attendance Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-secondary">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $row['class'] }}</td>
                            <td class="text-success">{{ $row['present'] }}</td>
                            <td class="text-danger">{{ $row['absent'] }}</td>
                            <td class="text-warning">{{ $row['late'] }}</td>
                            <td>{{ $row['excused'] }}</td>
                            <td>{{ $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
