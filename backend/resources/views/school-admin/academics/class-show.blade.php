@extends('layouts.portal')

@section('title', $class->name.' — ElimuGrid')
@section('page-title', $class->name)

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'academics'])
@endsection

@section('content')
    <a href="{{ route('school-admin.academics.index', ['tab' => 'classes']) }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Classes') }}
    </a>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Total students') }}</div><div class="value">{{ $totals['all'] }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Active') }}</div><div class="value">{{ $totals['active'] }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Boys / Girls') }}</div><div class="value">{{ $totals['male'] }} / {{ $totals['female'] }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Streams') }}</div><div class="value">{{ $class->streams->count() }}</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white">{{ __('Streams in this class') }}</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Stream') }}</th>
                        <th>{{ __('Students') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Boys / Girls') }}</th>
                        <th>{{ __('Capacity') }}</th>
                        <th style="min-width: 160px;">{{ __('Filled') }}</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($streamRows as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] }}</td>
                            <td>{{ $row['total'] }}</td>
                            <td>{{ $row['active'] }}</td>
                            <td>{{ $row['male'] }} / {{ $row['female'] }}</td>
                            <td>{{ $row['capacity'] ?? '—' }}</td>
                            <td>
                                @if ($row['capacity'])
                                    @php $pct = min(100, round($row['active'] / $row['capacity'] * 100)); @endphp
                                    <div class="progress" style="height: 8px;"><div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : 'bg-success' }}" style="width: {{ $pct }}%"></div></div>
                                    <div class="small text-secondary">{{ $pct }}%</div>
                                @else
                                    <span class="text-secondary small">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('school-admin.academics.classes.show', [$class, 'stream_id' => $row['id'] ?? 'none']) }}" class="btn btn-outline-secondary btn-sm">{{ __('View students') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    @if ($streamRows->isEmpty())
                        <tr><td colspan="7" class="text-secondary text-center py-4">{{ __('No streams yet — students belong directly to the class.') }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
                {{ __('Students') }}
                @if ($filterLabel)<span class="badge bg-light text-secondary border ms-1">{{ $filterLabel }}</span>@endif
            </span>
            @if (request()->filled('stream_id'))
                <a href="{{ route('school-admin.academics.classes.show', $class) }}" class="btn btn-outline-secondary btn-sm">{{ __('Show all') }}</a>
            @endif
        </div>
        @if ($students->isEmpty())
            <div class="card-body text-center text-secondary py-4">{{ __('No students to show.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>{{ __('Admission No.') }}</th><th>{{ __('Name') }}</th><th>{{ __('Gender') }}</th><th>{{ __('Stream') }}</th><th>{{ __('Status') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td class="text-secondary">{{ $students->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $student->admission_number }}</td>
                                <td>{{ $student->fullName() }}</td>
                                <td class="text-secondary small">{{ ucfirst($student->gender) }}</td>
                                <td class="text-secondary small">{{ $student->stream?->name ?? '—' }}</td>
                                <td><span class="badge badge-status-{{ $student->status === 'active' ? 'active' : 'suspended' }} rounded-pill px-2 py-1">{{ ucfirst($student->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('school-admin.partials.table-pagination', ['paginator' => $students])
        @endif
    </div>
@endsection
