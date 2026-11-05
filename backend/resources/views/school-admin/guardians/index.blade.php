@extends('layouts.portal')

@section('title', 'Guardians — ElimuGrid')
@section('page-title', __('Guardians'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'guardians'])
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
                       placeholder="{{ __('Search name, phone, email…') }}" value="{{ request('search') }}" style="max-width: 260px;">
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
        </div>

        @if ($guardians->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-people display-6 d-block mb-2"></i>
                {{ __('No guardians match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Children') }}</th>
                        <th>{{ __('Login') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($guardians as $guardian)
                        <tr>
                            <td class="text-secondary">{{ $guardians->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $guardian->name }}</td>
                            <td>{{ $guardian->phone }}</td>
                            <td class="text-secondary small">{{ $guardian->email ?? '—' }}</td>
                            <td class="text-secondary small">
                                {{ $guardian->students->map(fn ($s) => trim("{$s->first_name} {$s->last_name}"))->implode(', ') ?: '—' }}
                            </td>
                            <td>
                                @if ($guardian->user_id)
                                    <span class="badge {{ $guardian->user->status === 'suspended' ? 'badge-status-suspended' : 'badge-status-active' }} rounded-pill px-3 py-2">
                                        {{ $guardian->user->status === 'suspended' ? __('Suspended') : __('Active') }}
                                    </span>
                                @else
                                    <span class="badge badge-status-pending rounded-pill px-3 py-2">{{ __('No login') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#editGuardianModal{{ $guardian->id }}">
                                    {{ __('Edit') }}
                                </button>
                                <form method="POST" action="{{ route('school-admin.guardians.destroy', $guardian) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Delete :p1? This also removes any login they have.', ['p1' => $guardian->name]) }}');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $guardians])
        @endif
    </div>

    @foreach ($guardians as $guardian)
        <div class="modal fade" id="editGuardianModal{{ $guardian->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.guardians.update', $guardian) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('Edit Guardian') }} — {{ $guardian->name }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ $guardian->name }}" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" name="phone" class="form-control" value="{{ $guardian->phone }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Email') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <input type="email" name="email" class="form-control" value="{{ $guardian->email }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Occupation') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                            <input type="text" name="occupation" class="form-control" value="{{ $guardian->occupation }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Address') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                            <textarea name="address" class="form-control" rows="2">{{ $guardian->address }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Preferred Language') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                            <select name="locale" class="form-select">
                                <option value="">{{ __('School default') }}</option>
                                @foreach (config('app.supported_locales') as $code => $label)
                                    <option value="{{ $code }}" @selected($guardian->locale === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
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
@endsection
