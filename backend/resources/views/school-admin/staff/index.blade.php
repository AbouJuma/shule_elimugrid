@extends('layouts.portal')

@section('title', 'Staff — ElimuGrid')
@section('page-title', __('Staff'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'staff'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search name…') }}" value="{{ request('search') }}" style="max-width: 200px;">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option>
                    <option value="suspended" @selected(request('status')==='suspended')>{{ __('Suspended') }}</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
            @can('hr.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newStaffModal">
                    <i class="bi bi-person-plus me-1"></i> {{ __('Add Staff') }}
                </button>
            @endcan
        </div>

        @if ($staff->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-person-badge display-6 d-block mb-2"></i>
                {{ __('No staff match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Employee No.') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Job Title') }}</th>
                        <th>{{ __('Employment') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staff as $member)
                        <tr>
                            <td class="text-secondary">{{ $staff->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $member->staffProfile->employee_number }}</td>
                            <td>
                                <div>{{ $member->name }}</div>
                                <div class="text-secondary small">{{ $member->email }}</div>
                            </td>
                            <td class="text-secondary small">{{ $member->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td class="text-secondary small">{{ $member->staffProfile->job_title ?? '—' }}</td>
                            <td class="text-secondary small">{{ str_replace('_', ' ', ucfirst($member->staffProfile->employment_type)) }}</td>
                            <td>
                                <span class="badge {{ $member->status === 'active' ? 'badge-status-active' : 'badge-status-suspended' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('school-admin.staff.qr', $member) }}" target="_blank" class="btn btn-outline-secondary btn-sm" title="{{ __('Attendance QR') }}">
                                    <i class="bi bi-upc-scan"></i>
                                </a>
                                @can('hr.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#editStaffModal{{ $member->id }}">
                                        {{ __('Edit') }}
                                    </button>
                                @endcan
                                @can('users.suspend')
                                    <form method="POST" action="{{ route('school-admin.staff.toggle-status', $member) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                            {{ $member->status === 'active' ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $staff])
        @endif
    </div>

    @can('hr.manage')
        @foreach ($staff as $member)
            <div class="modal fade" id="editStaffModal{{ $member->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.staff.update', $member->staffProfile) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title h5">Edit Profile — {{ $member->name }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Name') }}</label>
                                        <input type="text" name="name" class="form-control" value="{{ $member->name }}" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Email') }}</label>
                                        <input type="email" name="email" class="form-control" value="{{ $member->email }}" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Role') }}</label>
                                    @if ($member->id === auth()->id())
                                        <input type="text" class="form-control" value="{{ $member->roles->pluck('name')->implode(', ') }}" disabled>
                                        <div class="form-text">{{ __("You can't change your own role.") }}</div>
                                    @else
                                        <select name="role" class="form-select" required>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->name }}" @selected($member->roles->pluck('name')->contains($role->name))>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Job Title') }}</label>
                                        <input type="text" name="job_title" class="form-control" value="{{ $member->staffProfile->job_title }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Department') }}</label>
                                        <input type="text" name="department" class="form-control" value="{{ $member->staffProfile->department }}">
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Employment Type') }}</label>
                                        <select name="employment_type" class="form-select" required>
                                            <option value="full_time" @selected($member->staffProfile->employment_type==='full_time')>{{ __('Full time') }}</option>
                                            <option value="part_time" @selected($member->staffProfile->employment_type==='part_time')>{{ __('Part time') }}</option>
                                            <option value="contract" @selected($member->staffProfile->employment_type==='contract')>{{ __('Contract') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Date Joined') }}</label>
                                        <input type="date" name="date_joined" class="form-control" value="{{ $member->staffProfile->date_joined?->toDateString() }}" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Date Left') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="date" name="date_left" class="form-control" value="{{ $member->staffProfile->date_left?->toDateString() }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('National ID') }}</label>
                                    <input type="text" name="national_id" class="form-control" value="{{ $member->staffProfile->national_id }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Address') }}</label>
                                    <textarea name="address" class="form-control" rows="2">{{ $member->staffProfile->address }}</textarea>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Next of Kin') }}</label>
                                        <input type="text" name="next_of_kin_name" class="form-control" value="{{ $member->staffProfile->next_of_kin_name }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Next of Kin Phone') }}</label>
                                        <input type="text" name="next_of_kin_phone" class="form-control" value="{{ $member->staffProfile->next_of_kin_phone }}">
                                    </div>
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

        <div class="modal fade" id="newStaffModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('school-admin.staff.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Add Staff') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
                            @endif

                            <h3 class="h6 text-secondary text-uppercase mb-3">{{ __('Account') }}</h3>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Full name') }}</label>
                                    <input type="text" name="user[name]" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Email') }}</label>
                                    <input type="email" name="user[email]" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Phone') }}</label>
                                    <input type="text" name="user[phone]" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Role') }}</label>
                                    <select name="user[role]" class="form-select" required>
                                        <option value="">{{ __('Select role…') }}</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <h3 class="h6 text-secondary text-uppercase mb-3">{{ __('Employment') }}</h3>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Job Title') }}</label>
                                    <input type="text" name="profile[job_title]" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Department') }}</label>
                                    <input type="text" name="profile[department]" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Employment Type') }}</label>
                                    <select name="profile[employment_type]" class="form-select" required>
                                        <option value="full_time">{{ __('Full time') }}</option>
                                        <option value="part_time">{{ __('Part time') }}</option>
                                        <option value="contract">{{ __('Contract') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Date Joined') }}</label>
                                    <input type="date" name="profile[date_joined]" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('National ID') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="text" name="profile[national_id]" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Next of Kin') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="text" name="profile[next_of_kin_name]" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Next of Kin Phone') }}</label>
                                    <input type="text" name="profile[next_of_kin_phone]" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Address') }}</label>
                                    <input type="text" name="profile[address]" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add Staff') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newStaffModal')).show();
                });
            </script>
        @endif
    @endcan
@endsection
