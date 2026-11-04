@extends('layouts.portal')

@section('title', 'Subjects — ElimuGrid')
@section('page-title', __('Subjects'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'subjects'])
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
            <form method="GET" class="d-flex" style="max-width: 280px;">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search subjects…') }}" value="{{ request('search') }}">
            </form>
            @can('subjects.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newSubjectModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Subject') }}
                </button>
            @endcan
        </div>

        @if ($subjects->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                @if (request('search'))
                    No subjects match "{{ request('search') }}".
                @else
                    No subjects yet.
                @endif
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th style="width: 1%;">#</th><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Classes') }}</th><th class="text-end">{{ __('Actions') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                        <tr>
                            <td class="text-secondary">{{ $subjects->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $subject->name }}</td>
                            <td class="text-secondary">{{ $subject->code ?? '—' }}</td>
                            <td class="text-secondary small">{{ $subject->classes->pluck('name')->join(', ') ?: 'None yet' }}</td>
                            <td class="text-end">
                                @can('subjects.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editSubjectModal{{ $subject->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.subjects.destroy', $subject) }}" class="d-inline"
                                          onsubmit="return confirm('Delete subject \'{{ $subject->name }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $subjects])
        @endif
    </div>

    @can('subjects.manage')
        @foreach ($subjects as $subject)
            <div class="modal fade" id="editSubjectModal{{ $subject->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.subjects.update', $subject) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Subject') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $subject->name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Code') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="text" name="code" class="form-control" value="{{ $subject->code }}">
                                </div>
                                <div class="mb-1">
                                    <label class="form-label">{{ __('Taught in') }}</label>
                                    @foreach ($classes as $class)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="school_class_ids[]"
                                                   value="{{ $class->id }}" id="edit-class-{{ $subject->id }}-{{ $class->id }}"
                                                   @checked($subject->classes->contains('id', $class->id))>
                                            <label class="form-check-label small" for="edit-class-{{ $subject->id }}-{{ $class->id }}">{{ $class->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach
    @endcan

    @can('subjects.manage')
        <div class="modal fade" id="newSubjectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.subjects.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Subject') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('Mathematics') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Code') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <input type="text" name="code" class="form-control" placeholder="{{ __('MATH') }}">
                            </div>
                            <div class="mb-1">
                                <label class="form-label">{{ __('Taught in') }}</label>
                                @foreach ($classes as $class)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="school_class_ids[]"
                                               value="{{ $class->id }}" id="class-{{ $class->id }}">
                                        <label class="form-check-label small" for="class-{{ $class->id }}">{{ $class->name }}</label>
                                    </div>
                                @endforeach
                                @if ($classes->isEmpty())
                                    <div class="text-secondary small">{{ __('No classes set up yet.') }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create Subject') }}</button>
                        </div>
                    </form>
            </div>
        </div>
    @endcan
@endsection
