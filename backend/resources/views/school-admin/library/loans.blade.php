@extends('layouts.portal')

@section('title', 'Loans — ElimuGrid')
@section('page-title', __('Book Loans'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'library'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    <a href="{{ route('school-admin.library.index') }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Catalog') }}
    </a>

    <div class="card">
        <div class="card-header bg-white">
            <form method="GET">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="borrowed" @selected(request('status')==='borrowed')>{{ __('Borrowed') }}</option>
                    <option value="returned" @selected(request('status')==='returned')>{{ __('Returned') }}</option>
                    <option value="lost" @selected(request('status')==='lost')>{{ __('Lost') }}</option>
                </select>
            </form>
        </div>

        @if ($loans->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No loans match these filters.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Book') }}</th>
                        <th>{{ __('Student') }}</th>
                        <th>{{ __('Borrowed') }}</th>
                        <th>{{ __('Due') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Fine') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($loans as $loan)
                        <tr>
                            <td class="text-secondary">{{ $loans->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $loan->book->title }}</td>
                            <td>{{ $loan->student->fullName() }}</td>
                            <td class="text-secondary small">{{ $loan->borrowed_date->format('d M Y') }}</td>
                            <td class="text-secondary small">{{ $loan->due_date->format('d M Y') }}</td>
                            <td>
                                @php
                                    $badge = match ($loan->status) {
                                        'returned' => 'badge-status-active',
                                        'lost' => 'badge-status-suspended',
                                        default => $loan->isOverdue() ? 'badge-status-suspended' : 'badge-status-pending',
                                    };
                                    $label = $loan->status === 'borrowed' && $loan->isOverdue() ? 'Overdue' : ucfirst($loan->status);
                                @endphp
                                <span class="badge {{ $badge }} rounded-pill px-3 py-2">{{ $label }}</span>
                            </td>
                            <td class="text-secondary small">{{ $loan->fine_amount ? number_format($loan->fine_amount, 2) : '—' }}</td>
                            <td class="text-end">
                                @can('library.manage')
                                    @if ($loan->status === 'borrowed')
                                        <form method="POST" action="{{ route('school-admin.library.loans.return', $loan) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Return') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('school-admin.library.loans.lost', $loan) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Mark Lost') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $loans])
        @endif
    </div>
@endsection
