@extends('layouts.portal')

@section('title', 'Lessons — ElimuGrid')
@section('page-title', __('Lessons'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'lessons'])
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
            @can('lessons.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newLessonModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('Post Lesson') }}
                </button>
            @endcan
        </div>

        @if ($lessons->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-play-btn display-6 d-block mb-2"></i>
                {{ __('No lessons posted yet.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Class / Subject') }}</th>
                        <th>{{ __('Video') }}</th>
                        <th>{{ __('Posted') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lessons as $lesson)
                        <tr>
                            <td class="fw-semibold">
                                {{ $lesson->title }}
                                @if ($lesson->description)
                                    <div class="text-secondary small">{{ \Illuminate\Support\Str::limit($lesson->description, 80) }}</div>
                                @endif
                            </td>
                            <td class="text-secondary small">
                                {{ $lesson->schoolClass?->name ?? '—' }}
                                @if ($lesson->subject) / {{ $lesson->subject->name }} @endif
                            </td>
                            <td>
                                @if ($lesson->video_url)
                                    <a href="{{ $lesson->video_url }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-link-45deg"></i> {{ __('Link') }}
                                    </a>
                                @elseif ($lesson->hasUploadedVideo())
                                    <a href="{{ route('school-admin.lessons.video', $lesson) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-play-fill"></i> {{ __('Play') }}
                                    </a>
                                @endif
                            </td>
                            <td class="text-secondary small">{{ $lesson->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                @can('lessons.manage')
                                    <form method="POST" action="{{ route('school-admin.lessons.destroy', $lesson) }}" class="d-inline"
                                          onsubmit="return confirm('{{ __('Delete this lesson?') }}');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $lessons])
        @endif
    </div>

    @can('lessons.manage')
        <div class="modal fade" id="newLessonModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.lessons.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Post Lesson') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Title') }}</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Description') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
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
                                <label class="form-label">{{ __('Video link') }}</label>
                                <input type="url" name="video_url" class="form-control" placeholder="https://youtu.be/…">
                            </div>
                            <div class="text-center text-secondary small mb-3">{{ __('— or —') }}</div>
                            <div class="mb-1">
                                <label class="form-label">{{ __('Upload a video file') }} <span class="text-secondary">{{ __('(max 200MB)') }}</span></label>
                                <input type="file" name="video" class="form-control" accept="video/*">
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
