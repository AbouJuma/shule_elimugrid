@extends('layouts.portal')

@section('title', 'Financial Report — ElimuGrid')
@section('page-title', __('Financial Report'))

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
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" placeholder="{{ __('From') }}">
                <span class="text-secondary small">{{ __('to') }}</span>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" placeholder="To">
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Filter') }}</button>
            </form>
            <a href="{{ route('school-admin.reports.financial', ['export' => 'csv', 'from' => $from, 'to' => $to]) }}" class="btn btn-outline-secondary btn-sm">
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
                        <th>{{ __('Invoiced') }}</th>
                        <th>{{ __('Collected') }}</th>
                        <th>{{ __('Outstanding') }}</th>
                        <th>{{ __('Collection Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-secondary">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $row['class'] }}</td>
                            <td>{{ number_format($row['invoiced'], 2) }}</td>
                            <td class="text-success">{{ number_format($row['collected'], 2) }}</td>
                            <td class="text-danger">{{ number_format($row['outstanding'], 2) }}</td>
                            <td>{{ $row['collection_rate'] !== null ? $row['collection_rate'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="fw-semibold border-top">
                        <td></td>
                        <td>{{ __('Total') }}</td>
                        <td>{{ number_format($rows->sum('invoiced'), 2) }}</td>
                        <td>{{ number_format($rows->sum('collected'), 2) }}</td>
                        <td>{{ number_format($rows->sum('outstanding'), 2) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
@endsection
