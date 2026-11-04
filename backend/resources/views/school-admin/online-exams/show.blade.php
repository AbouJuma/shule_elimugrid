@extends('layouts.portal')

@section('title', $exam->title.' — ElimuGrid')
@section('page-title', $exam->title)

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'online-exams'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-secondary small">
            {{ $exam->schoolClass?->name ?? '—' }}
            @if ($exam->subject) / {{ $exam->subject->name }} @endif
            — {{ ucfirst($exam->mode) }}
            <span class="badge {{ $exam->status === 'published' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2 ms-2">
                {{ ucfirst($exam->status) }}
            </span>
        </div>
        @can('online_exams.manage')
            @if (! $exam->isPublished())
                <form method="POST" action="{{ route('school-admin.online-exams.publish', $exam) }}"
                      onsubmit="return confirm('{{ __('Publish this exam? Students will be able to take it once published.') }}');">
                    @csrf
                    <button type="submit" class="btn btn-gold btn-sm">{{ __('Publish Exam') }}</button>
                </form>
            @endif
        @endcan
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white">{{ __('Questions') }} ({{ $exam->questions->count() }})</div>
        <div class="card-body">
            @foreach ($exam->questions as $i => $question)
                <div class="mb-3 @unless ($loop->last) pb-3 border-bottom @endunless">
                    <div class="fw-semibold">{{ $i + 1 }}. {{ $question->question_text }} <span class="text-secondary small">({{ __(':n pt', ['n' => $question->points]) }})</span></div>
                    <ul class="list-unstyled ms-3 mb-0 mt-1">
                        @foreach ($question->choices as $choice)
                            <li class="{{ $choice->is_correct ? 'text-success fw-semibold' : 'text-secondary' }}">
                                @if ($choice->is_correct)<i class="bi bi-check-circle-fill me-1"></i>@else<i class="bi bi-circle me-1"></i>@endif
                                {{ $choice->choice_text }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">{{ __('Submitted Attempts') }} ({{ $attempts->count() }})</div>
        @if ($attempts->isEmpty())
            <div class="card-body text-center text-secondary py-4">{{ __('No one has completed this exam yet.') }}</div>
        @else
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>{{ __('Student') }}</th><th>{{ __('Score') }}</th><th>{{ __('Submitted') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($attempts as $attempt)
                        <tr>
                            <td>{{ $attempt->student?->fullName() ?? '—' }}</td>
                            <td>{{ $attempt->score }} / {{ $attempt->max_score }}</td>
                            <td class="text-secondary small">{{ $attempt->submitted_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
