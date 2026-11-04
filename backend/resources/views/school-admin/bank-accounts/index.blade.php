@extends('layouts.portal')

@section('title', 'Bank Accounts — ElimuGrid')
@section('page-title', __('Bank Accounts'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'bank-accounts'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="text-secondary small">{{ $accounts->total() }} account{{ $accounts->total() === 1 ? '' : 's' }}</span>
            @can('bank_accounts.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newAccountModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Account') }}
                </button>
            @endcan
        </div>

        @if ($accounts->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No bank accounts yet.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th style="width: 1%;">#</th><th>{{ __('Bank') }}</th><th>{{ __('Account Name') }}</th><th>{{ __('Account Number') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Currency') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr>
                            <td class="text-secondary">{{ $accounts->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $account->bank_name }}</td>
                            <td>{{ $account->account_name }}</td>
                            <td class="text-secondary">{{ $account->account_number }}</td>
                            <td class="text-secondary small">{{ $account->purpose ?? '—' }}</td>
                            <td>{{ $account->currency }}</td>
                            <td>
                                <span class="badge badge-status-{{ $account->is_active ? 'active' : 'suspended' }} rounded-pill px-2 py-1">
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('bank_accounts.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editAccountModal{{ $account->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.bank-accounts.destroy', $account) }}" class="d-inline"
                                          onsubmit="return confirm('Delete this bank account?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $accounts])
        @endif
    </div>

    @can('bank_accounts.manage')
        @foreach ($accounts as $account)
            <div class="modal fade" id="editAccountModal{{ $account->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.bank-accounts.update', $account) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Bank Account') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Bank Name') }}</label>
                                    <input type="text" name="bank_name" class="form-control" value="{{ $account->bank_name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Account Name') }}</label>
                                    <input type="text" name="account_name" class="form-control" value="{{ $account->account_name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Account Number') }}</label>
                                    <input type="text" name="account_number" class="form-control" value="{{ $account->account_number }}" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Currency') }}</label>
                                        <input type="text" name="currency" class="form-control" value="{{ $account->currency }}" maxlength="3">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Purpose') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                        <input type="text" name="purpose" class="form-control" value="{{ $account->purpose }}">
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive{{ $account->id }}" @checked($account->is_active)>
                                    <label class="form-check-label small" for="isActive{{ $account->id }}">{{ __('Active') }}</label>
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

    @can('bank_accounts.manage')
        <div class="modal fade" id="newAccountModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.bank-accounts.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Bank Account') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Bank Name') }}</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="{{ __('CRDB') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Account Name') }}</label>
                                <input type="text" name="account_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Account Number') }}</label>
                                <input type="text" name="account_number" class="form-control" required>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Currency') }}</label>
                                    <input type="text" name="currency" class="form-control" value="TZS" maxlength="3">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Purpose') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="text" name="purpose" class="form-control" placeholder="{{ __('School Fees') }}">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add Account') }}</button>
                        </div>
                    </form>
            </div>
        </div>
    @endcan
@endsection
