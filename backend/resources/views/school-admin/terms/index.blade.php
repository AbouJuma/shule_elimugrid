@extends('layouts.portal')

@section('title', __('Terms').' — ElimuGrid')
@section('page-title', __('Terms'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'terms'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <p class="text-secondary small">{{ __('Each school year can have up to :max terms. Exams are attached to a term, and report cards are built per term.', ['max' => $maxTerms]) }}</p>

    @forelse ($years as $year)
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>{{ $year->name }}
                    @if ($year->is_current)<span class="badge badge-status-active rounded-pill ms-1">{{ __('Current') }}</span>@endif
                    <span class="text-secondary small ms-2">{{ $year->terms->count() }} / {{ $maxTerms }}</span>
                </span>
                @can('academic_years.manage')
                    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newTermModal{{ $year->id }}"
                            @disabled($year->terms->count() >= $maxTerms)>
                        <i class="bi bi-plus-lg"></i> {{ __('Add Term') }}
                    </button>
                @endcan
            </div>
            @if ($year->terms->isEmpty())
                <div class="card-body text-secondary small">{{ __('No terms yet.') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Term') }}</th><th>{{ __('Starts') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($year->terms as $term)
                                <tr>
                                    <td class="fw-semibold">{{ $term->name }}</td>
                                    <td class="text-secondary small">{{ $term->start_date->format('d M Y') }}</td>
                                    <td class="text-secondary small">{{ $term->end_date->format('d M Y') }}</td>
                                    <td>@if ($term->is_current)<span class="badge badge-status-active rounded-pill px-2 py-1">{{ __('Current') }}</span>@endif</td>
                                    <td class="text-end">
                                        @can('academic_years.manage')
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editTermModal{{ $term->id }}"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" action="{{ route('school-admin.terms.destroy', $term) }}" class="d-inline"
                                                  onsubmit="return confirm('{{ __('Delete this term?') }}');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @empty
        <div class="card"><div class="card-body text-secondary text-center py-5">
            {{ __('Create an academic year first.') }} <a href="{{ route('school-admin.academics.index') }}">{{ __('Academic Setup') }}</a>
        </div></div>
    @endforelse

    @can('academic_years.manage')
        @foreach ($years as $year)
            <div class="modal fade" id="newTermModal{{ $year->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.terms.store') }}">
                            @csrf
                            <input type="hidden" name="academic_year_id" value="{{ $year->id }}">
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Add Term') }} — {{ $year->name }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" placeholder="{{ __('Term 1') }}" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6"><label class="form-label">{{ __('Starts') }}</label><input type="date" name="start_date" class="form-control" required></div>
                                    <div class="col-6"><label class="form-label">{{ __('Ends') }}</label><input type="date" name="end_date" class="form-control" required></div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_current" value="1" id="termCur{{ $year->id }}">
                                    <label class="form-check-label small" for="termCur{{ $year->id }}">{{ __('Set as current term') }}</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Add Term') }}</button>
                            </div>
                        </form>
                </div>
            </div>

            @foreach ($year->terms as $term)
                <div class="modal fade" id="editTermModal{{ $term->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('school-admin.terms.update', $term) }}">
                                @csrf @method('PUT')
                                <div class="modal-header">
                                    <h2 class="modal-title h5">{{ __('Edit Term') }}</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('Name') }}</label>
                                        <input type="text" name="name" class="form-control" value="{{ $term->name }}" required>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-6"><label class="form-label">{{ __('Starts') }}</label><input type="date" name="start_date" class="form-control" value="{{ $term->start_date->toDateString() }}" required></div>
                                        <div class="col-6"><label class="form-label">{{ __('Ends') }}</label><input type="date" name="end_date" class="form-control" value="{{ $term->end_date->toDateString() }}" required></div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_current" value="1" id="termEditCur{{ $term->id }}" @checked($term->is_current)>
                                        <label class="form-check-label small" for="termEditCur{{ $term->id }}">{{ __('Set as current term') }}</label>
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
        @endforeach
    @endcan
@endsection
