@extends('layouts.portal')

@section('title', $exam->name.' — ElimuGrid')
@section('page-title', $exam->name)

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'exams'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-secondary small">
            {{ $exam->classes->pluck('name')->join(', ') }} — {{ $exam->academicYear->name }}
            <span class="badge {{ $exam->status === 'published' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2 ms-2">
                {{ ucfirst($exam->status) }}
            </span>
        </div>
        @can('exams.publish')
            @if (! $exam->isPublished())
                <form method="POST" action="{{ route('school-admin.exams.publish', $exam) }}"
                      onsubmit="return confirm('Publish this exam? Results become visible once published.');">
                    @csrf
                    <button type="submit" class="btn btn-gold btn-sm">{{ __('Publish Exam') }}</button>
                </form>
            @endif
        @endcan
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            Subjects
            @can('exams.manage')
                @if ($availableSubjects->isNotEmpty())
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="bi bi-plus-lg"></i> {{ __('Add Subject') }}
                    </button>
                @endif
            @endcan
        </div>

        @if ($exam->subjects->isEmpty())
            <div class="card-body text-center text-secondary py-4">
                No subjects added yet.
                @if ($availableSubjects->isEmpty())
                    <div class="small mt-1">
                        No subjects are taught in {{ $exam->classes->pluck('name')->join(', ') }} yet —
                        <a href="{{ route('school-admin.subjects.index') }}">{{ __('set some up first') }}</a>.
                    </div>
                @endif
            </div>
        @else
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>{{ __('Subject') }}</th><th>{{ __('Max Marks') }}</th><th class="text-end">{{ __('Marks Entry') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($exam->subjects as $subject)
                        <tr>
                            <td class="fw-semibold">{{ $subject->name }}</td>
                            <td>{{ $subject->pivot->max_marks }}</td>
                            <td class="text-end">
                                @can('exams.record_marks')
                                    {{-- One class at a time: an exam spanning several classes needs
                                         a class picked first, so only that class's students show. --}}
                                    @foreach ($exam->classes as $class)
                                        <a href="{{ route('school-admin.exams.marks.index', [$exam, $subject, 'school_class_id' => $class->id]) }}"
                                           class="btn btn-outline-secondary btn-sm mb-1">
                                            {{ $exam->classes->count() > 1 ? $class->name : __('Enter Marks') }}
                                        </a>
                                    @endforeach
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @can('exams.manage')
        <div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.exams.subjects.store', $exam) }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Add Subject') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Subject') }}</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="">{{ __('Select subject…') }}</option>
                                    @foreach ($availableSubjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Max Marks') }}</label>
                                <input type="number" name="max_marks" class="form-control" value="100" min="1" max="1000" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add') }}</button>
                        </div>
                    </form>
            </div>
        </div>
    @endcan
@endsection
