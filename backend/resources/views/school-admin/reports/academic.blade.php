@extends('layouts.portal')

@section('title', 'Academic Report — ElimuGrid')
@section('page-title', __('Academic Report'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'reports'])
@endsection

@section('content')
    <a href="{{ route('school-admin.reports.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Reports') }}
    </a>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 220px;">
                    <option value="">{{ __('Select exam…') }}</option>
                    @foreach ($exams as $exam)
                        <option value="{{ $exam->id }}" @selected($selectedExamId == $exam->id)>
                            {{ $exam->name }} ({{ $exam->classes->pluck('name')->join(', ') ?: '—' }})
                        </option>
                    @endforeach
                </select>
            </form>
            @if ($selectedExamId)
                <a href="{{ route('school-admin.reports.academic', ['export' => 'csv', 'exam_id' => $selectedExamId]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i> {{ __('Export CSV') }}
                </a>
            @endif
        </div>
        @if ($exams->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No exams have been created yet.') }}</div>
        @elseif ($rows->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('This exam has no subjects added yet.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Max Marks') }}</th>
                        <th>{{ __('Entries') }}</th>
                        <th>{{ __('Average') }}</th>
                        <th>{{ __('Highest') }}</th>
                        <th>{{ __('Lowest') }}</th>
                        <th>{{ __('Pass Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-secondary">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $row['subject'] }}</td>
                            <td>{{ $row['max_marks'] }}</td>
                            <td>{{ $row['entries'] }}</td>
                            <td>{{ $row['average'] ?? '—' }}</td>
                            <td>{{ $row['highest'] ?? '—' }}</td>
                            <td>{{ $row['lowest'] ?? '—' }}</td>
                            <td>{{ $row['pass_rate'] !== null ? $row['pass_rate'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
