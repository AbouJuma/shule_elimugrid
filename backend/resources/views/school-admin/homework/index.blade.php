@extends('layouts.portal')

@section('title', 'Homework — ElimuGrid')
@section('page-title', __('Homework'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'homework'])
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
                <select name="school_class_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 180px;">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('school_class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </form>
            @can('homework.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newHomeworkModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('Post Homework') }}
                </button>
            @endcan
        </div>

        @if ($homeworks->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-journal-check display-6 d-block mb-2"></i>
                {{ __('No homework posted yet.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Class / Subject') }}</th>
                        <th>{{ __('Due') }}</th>
                        <th>{{ __('Attachment') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($homeworks as $homework)
                        <tr>
                            <td class="fw-semibold">
                                {{ $homework->title }}
                                @if ($homework->instructions)
                                    <div class="text-secondary small">{{ \Illuminate\Support\Str::limit($homework->instructions, 80) }}</div>
                                @endif
                            </td>
                            <td class="text-secondary small">
                                {{ $homework->schoolClass?->name ?? '—' }}
                                @if ($homework->subject) / {{ $homework->subject->name }} @endif
                            </td>
                            <td class="text-secondary small">{{ $homework->due_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                @if ($homework->attachment_path)
                                    <a href="{{ route('school-admin.homework.attachment', $homework) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-paperclip"></i> {{ __('Open') }}
                                    </a>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('homework.manage')
                                    <form method="POST" action="{{ route('school-admin.homework.destroy', $homework) }}" class="d-inline"
                                          onsubmit="return confirm('{{ __('Delete this homework?') }}');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $homeworks])
        @endif
    </div>

    @can('homework.manage')
        <div class="modal fade" id="newHomeworkModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.homework.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Post Homework') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Title') }}</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Instructions') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <textarea name="instructions" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Class') }}</label>
                                    <select name="school_class_id" class="form-select" required>
                                        <option value="">{{ __('Select class…') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Subject') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="subject_id" class="form-select">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Due date') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <input type="date" name="due_date" class="form-control">
                            </div>
                            <div class="mb-1">
                                <label class="form-label">{{ __('Attachment') }} <span class="text-secondary">{{ __('(optional, PDF/Word/image, max 20MB)') }}</span></label>
                                <input type="file" name="attachment" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Post') }}</button>
                        </div>
                    </form>
            </div>
        </div>
    @endcan
@endsection
