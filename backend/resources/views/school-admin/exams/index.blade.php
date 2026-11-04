@extends('layouts.portal')

@section('title', 'Exams — ElimuGrid')
@section('page-title', __('Exams'))

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

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="school_class_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 160px;">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('school_class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="draft" @selected(request('status')==='draft')>{{ __('Draft') }}</option>
                    <option value="ongoing" @selected(request('status')==='ongoing')>{{ __('Ongoing') }}</option>
                    <option value="completed" @selected(request('status')==='completed')>{{ __('Completed') }}</option>
                    <option value="published" @selected(request('status')==='published')>{{ __('Published') }}</option>
                </select>
            </form>
            @can('exams.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newExamModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Exam') }}
                </button>
            @endcan
        </div>

        @if ($exams->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No exams match these filters.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th style="width: 1%;">#</th><th>{{ __('Exam') }}</th><th>{{ __('Class') }}</th><th>{{ __('Academic Year') }}</th><th>{{ __('Status') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($exams as $exam)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('school-admin.exams.show', $exam) }}'" style="cursor:pointer">
                            <td class="text-secondary">{{ $exams->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $exam->name }}</td>
                            <td>{{ $exam->classes->pluck('name')->join(', ') }}</td>
                            <td class="text-secondary small">{{ $exam->academicYear->name }}</td>
                            <td>
                                <span class="badge {{ $exam->status === 'published' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($exam->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $exams])
        @endif
    </div>

    @can('exams.manage')
        <div class="modal fade" id="newExamModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.exams.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Exam') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('Mid-Term Exam') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Classes') }} <span class="text-secondary">{{ __('(one exam can run for several)') }}</span></label>
                                <div class="border rounded p-2" style="max-height: 160px; overflow-y: auto;">
                                    @foreach ($classes as $class)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="school_class_ids[]"
                                                   value="{{ $class->id }}" id="exam-class-{{ $class->id }}">
                                            <label class="form-check-label small" for="exam-class-{{ $class->id }}">{{ $class->name }}</label>
                                        </div>
                                    @endforeach
                                    @if ($classes->isEmpty())
                                        <div class="text-secondary small">{{ __('No classes set up yet.') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Academic Year') }}</label>
                                <select name="academic_year_id" class="form-select" required>
                                    <option value="">{{ __('Select year…') }}</option>
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Term') }} <span class="text-secondary">{{ __('(needed for report cards)') }}</span></label>
                                <select name="term_id" class="form-select">
                                    <option value="">{{ __('No term') }}</option>
                                    @foreach ($terms as $term)
                                        <option value="{{ $term->id }}">{{ $term->academicYear?->name }} — {{ $term->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Weight in term') }} <span class="text-secondary">({{ __('default 1 — a weight of 2 counts double') }})</span></label>
                                <input type="number" step="0.1" min="0.1" max="100" name="weight" class="form-control" value="1">
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Start date') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="date" name="start_date" class="form-control">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('End date') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create Exam') }}</button>
                        </div>
                    </form>
            </div>
        </div>
    @endcan
@endsection
