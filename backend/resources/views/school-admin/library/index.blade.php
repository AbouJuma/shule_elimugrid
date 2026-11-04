@extends('layouts.portal')

@section('title', 'Library — ElimuGrid')
@section('page-title', __('Library'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'library'])
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
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search title or author…') }}" value="{{ request('search') }}" style="max-width: 220px;">
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
            <div class="d-flex gap-2">
                <a href="{{ route('school-admin.library.loans') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-clock-history me-1"></i> {{ __('Loans') }}
                </a>
                @can('library.manage')
                    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newBookModal">
                        <i class="bi bi-plus-lg me-1"></i> {{ __('New Book') }}
                    </button>
                @endcan
            </div>
        </div>

        @if ($books->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-journal-richtext display-6 d-block mb-2"></i>
                {{ __('No books match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Author') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Available') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($books as $book)
                        <tr>
                            <td class="text-secondary">{{ $books->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $book->title }}</td>
                            <td class="text-secondary small">{{ $book->author ?? '—' }}</td>
                            <td class="text-secondary small">{{ $book->category ?? '—' }}</td>
                            <td>
                                {{ $book->available_copies }} / {{ $book->total_copies }}
                                @if ($book->isFullyBorrowed())
                                    <span class="badge badge-status-pending rounded-pill px-2 py-1 ms-1">{{ __('None left') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('library.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#borrowModal{{ $book->id }}"
                                            @disabled($book->isFullyBorrowed())>
                                        {{ __('Issue') }}
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editBookModal{{ $book->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.library.destroy', $book) }}" class="d-inline"
                                          onsubmit="return confirm('Delete \'{{ $book->title }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $books])
        @endif
    </div>

    @can('library.manage')
        @foreach ($books as $book)
            <div class="modal fade" id="borrowModal{{ $book->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.library.borrow', $book) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5">Issue — {{ $book->title }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Student') }}</label>
                                    <select name="student_id" class="form-select" required>
                                        <option value="">{{ __('Select student…') }}</option>
                                        @foreach ($students as $student)
                                            <option value="{{ $student->id }}">{{ $student->fullName() }} ({{ $student->admission_number }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Due Date') }}</label>
                                    <input type="date" name="due_date" class="form-control" value="{{ now()->addWeeks(2)->toDateString() }}" min="{{ now()->toDateString() }}" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Issue Book') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        <div class="modal fade" id="newBookModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.library.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Book') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Title') }}</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Author') }}</label>
                                    <input type="text" name="author" class="form-control">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Category') }}</label>
                                    <input type="text" name="category" class="form-control">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('ISBN') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="text" name="isbn" class="form-control">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Total Copies') }}</label>
                                    <input type="number" min="1" name="total_copies" class="form-control" value="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add Book') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newBookModal')).show();
                });
            </script>
        @endif

        @foreach ($books as $book)
            <div class="modal fade" id="editBookModal{{ $book->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.library.update', $book) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Book') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Title') }}</label>
                                    <input type="text" name="title" class="form-control" value="{{ $book->title }}" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Author') }}</label>
                                        <input type="text" name="author" class="form-control" value="{{ $book->author }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Category') }}</label>
                                        <input type="text" name="category" class="form-control" value="{{ $book->category }}">
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('ISBN') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                        <input type="text" name="isbn" class="form-control" value="{{ $book->isbn }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Total Copies') }}</label>
                                        <input type="number" min="1" name="total_copies" class="form-control" value="{{ $book->total_copies }}" required>
                                    </div>
                                </div>
                                <p class="text-secondary small mt-3 mb-0">Currently {{ $book->total_copies - $book->available_copies }} of {{ $book->total_copies }} on loan.</p>
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
@endsection
