@extends('layouts.portal')

@section('title', 'Students — ElimuGrid')
@section('page-title', __('Students'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'students'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->hasAny(['status', 'reason', 'email', 'phone', 'guardian', 'name', 'relationship']))
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach (array_merge($errors->get('status'), $errors->get('reason'), $errors->get('email'), $errors->get('phone'), $errors->get('guardian'), $errors->get('name'), $errors->get('relationship')) as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search name or admission no…') }}" value="{{ request('search') }}" style="max-width: 220px;">
                <select name="school_class_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $filterClass)
                        <option value="{{ $filterClass->id }}" @selected(request('school_class_id') == $filterClass->id)>{{ $filterClass->name }}</option>
                    @endforeach
                </select>
                <select name="stream_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 170px;">
                    <option value="">{{ __('All streams') }}</option>
                    @foreach ($classes as $filterClass)
                        @foreach ($filterClass->streams as $filterStream)
                            <option value="{{ $filterStream->id }}" @selected(request('stream_id') == $filterStream->id)>{{ $filterClass->name }} — {{ $filterStream->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option>
                    <option value="graduated" @selected(request('status')==='graduated')>{{ __('Graduated') }}</option>
                    <option value="withdrawn" @selected(request('status')==='withdrawn')>{{ __('Withdrawn') }}</option>
                    <option value="transferred" @selected(request('status')==='transferred')>{{ __('Transferred') }}</option>
                    <option value="suspended" @selected(request('status')==='suspended')>{{ __('Suspended') }}</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
            @can('students.create')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#admitStudentModal">
                    <i class="bi bi-person-plus me-1"></i> {{ __('Admit Student') }}
                </button>
            @endcan
        </div>

        @if ($students->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-people display-6 d-block mb-2"></i>
                {{ __('No students yet.') }}
            </div>
        @else
            <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Admission No.') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Class / Stream') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td class="text-secondary">{{ $students->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="fw-semibold">{{ $student->admission_number }}</div>
                                @if ($student->control_number)
                                    <div class="text-secondary small" title="{{ __('Fee control number — permanent, the same on every invoice') }}">{{ __('CN') }} {{ $student->control_number }}</div>
                                @endif
                            </td>
                            <td>{{ $student->fullName() }}</td>
                            <td class="text-secondary small">
                                {{ $student->schoolClass?->name ?? '—' }}
                                @if ($student->stream) / {{ $student->stream->name }} @endif
                            </td>
                            <td>
                                <span class="badge badge-status-{{ $student->status === 'active' ? 'active' : 'suspended' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($student->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#viewStudentModal{{ $student->id }}" title="{{ __('View') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @can('students.update')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editStudentModal{{ $student->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endcan
                                @can('students.archive')
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#archiveStudentModal{{ $student->id }}" title="{{ __('Change status') }}">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                @endcan
                                @can('students.delete')
                                    <form method="POST" action="{{ route('school-admin.students.destroy', $student) }}" class="d-inline"
                                          onsubmit="return confirm('{{ __('Delete :p1? This removes them from every list — it can be undone, but only by a developer restoring the record. Prefer \'Change status\' if you just want to mark them withdrawn, graduated, etc.') }}'.replace(':p1', {{ Illuminate\Support\Js::from($student->fullName()) }}));">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('Delete') }}">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            @include('school-admin.partials.table-pagination', ['paginator' => $students])
        @endif
    </div>

    <div class="modal fade" id="admitStudentModal" tabindex="-1" aria-labelledby="admitStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('school-admin.students.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="admitStudentModalLabel">{{ __('Admit Student') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0 small">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ __($error) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($academicYears->isEmpty())
                            <div class="alert alert-warning">
                                {{ __('No academic year has been set up yet.') }} <a href="{{ route('school-admin.academics.index') }}">{{ __('Create one first') }}</a>.
                            </div>
                        @endif

                        <h3 class="h6 text-secondary text-uppercase mb-3">{{ __('Student details') }}</h3>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('First name') }}</label>
                                <input type="text" name="student[first_name]" class="form-control" value="{{ old('student.first_name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Last name') }}</label>
                                <input type="text" name="student[last_name]" class="form-control" value="{{ old('student.last_name') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Gender') }}</label>
                                <select name="student[gender]" class="form-select" required>
                                    <option value="">{{ __('Select…') }}</option>
                                    <option value="male" @selected(old('student.gender')==='male')>{{ __('Male') }}</option>
                                    <option value="female" @selected(old('student.gender')==='female')>{{ __('Female') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Date of birth') }}</label>
                                <input type="date" name="student[date_of_birth]" class="form-control" value="{{ old('student.date_of_birth') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Admission date') }}</label>
                                <input type="date" name="student[admission_date]" class="form-control" value="{{ old('student.admission_date', now()->toDateString()) }}" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ __('Academic year') }}</label>
                                <select name="student[academic_year_id]" class="form-select" required>
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year->id }}" @selected(old('student.academic_year_id')==$year->id)>{{ $year->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Class') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <select name="student[school_class_id]" class="form-select">
                                    <option value="">{{ __('Not yet assigned') }}</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}" @selected(old('student.school_class_id')==$class->id)>{{ $class->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Stream') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <select name="student[stream_id]" class="form-select">
                                    <option value="">{{ __('Not yet assigned') }}</option>
                                    @foreach ($classes as $class)
                                        @foreach ($class->streams as $stream)
                                            <option value="{{ $stream->id }}" @selected(old('student.stream_id')==$stream->id)>
                                                {{ $class->name }} — {{ $stream->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('Must match the class selected above.') }}</div>
                            </div>
                        </div>

                        <h3 class="h6 text-secondary text-uppercase mb-3">{{ __('Guardian') }}</h3>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">{{ __('Use an existing guardian') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <select name="guardians[0][guardian_id]" class="form-select" data-existing-guardian-select>
                                    <option value="">{{ __('— None, add new guardian below —') }}</option>
                                    @foreach ($guardians as $guardian)
                                        <option value="{{ $guardian->id }}">{{ $guardian->name }} ({{ $guardian->phone }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 collapse show" data-new-guardian-fields>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Full name') }}</label>
                                        <input type="text" name="guardians[0][name]" class="form-control" value="{{ old('guardians.0.name') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Phone') }}</label>
                                        <input type="text" name="guardians[0][phone]" class="form-control" value="{{ old('guardians.0.phone') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Email') }}</label>
                                        <input type="email" name="guardians[0][email]" class="form-control" value="{{ old('guardians.0.email') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Relationship') }}</label>
                                        <select name="guardians[0][relationship]" class="form-select">
                                            <option value="father">{{ __('Father') }}</option>
                                            <option value="mother">{{ __('Mother') }}</option>
                                            <option value="guardian" selected>{{ __('Guardian') }}</option>
                                            <option value="other">{{ __('Other') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Language for messages') }}</label>
                                        <select name="guardians[0][locale]" class="form-select">
                                            <option value="">{{ __('School default') }}</option>
                                            @foreach (config('app.supported_locales') as $code => $label)
                                                <option value="{{ $code }}" @selected(old('guardians.0.locale') === $code)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <input type="hidden" name="guardians[0][is_primary]" value="1">
                                <input type="hidden" name="notify_guardian" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="notify_guardian" value="1" id="notifyGuardian" checked>
                                    <label class="form-check-label" for="notifyGuardian">{{ __('Send the parent a message with the fees to pay (amount, control number, bank details)') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-gold">{{ __('Admit Student') }}</button>
                    </div>
                </form>
        </div>
    </div>

    @foreach ($students as $student)
        <div class="modal fade" id="viewStudentModal{{ $student->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ $student->fullName() }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#studentDetailsPane{{ $student->id }}" type="button">{{ __('Details') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#studentGuardiansPane{{ $student->id }}" type="button">{{ __('Guardians') }}</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                        <div class="tab-pane fade show active" id="studentDetailsPane{{ $student->id }}">
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <span class="badge badge-status-{{ $student->status === 'active' ? 'active' : 'suspended' }} rounded-pill px-3 py-2">{{ ucfirst($student->status) }}</span>
                            @if ($student->user_id)
                                <span class="badge bg-light text-secondary rounded-pill px-3 py-2"><i class="bi bi-check-circle text-success"></i> {{ __('Has own login') }}</span>
                            @endif
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-4"><div class="text-secondary small">{{ __('Admission No.') }}</div><div class="fw-semibold">{{ $student->admission_number }}</div></div>
                            <div class="col-sm-4"><div class="text-secondary small">{{ __('Fee control number') }}</div><div class="fw-semibold">{{ $student->control_number ?? '—' }}</div></div>
                            <div class="col-sm-4"><div class="text-secondary small">{{ __('Admission date') }}</div><div>{{ optional($student->admission_date)->format('d M Y') ?? '—' }}</div></div>
                            <div class="col-sm-4">
                                <div class="text-secondary small">{{ __('Attendance QR') }}</div>
                                <a href="{{ route('school-admin.students.qr', $student) }}" target="_blank" class="btn btn-outline-secondary btn-sm mt-1">
                                    <i class="bi bi-upc-scan"></i> {{ __('View / Print') }}
                                </a>
                            </div>
                            <div class="col-sm-4"><div class="text-secondary small">{{ __('Class / Stream') }}</div><div>{{ $student->schoolClass?->name ?? '—' }}{{ $student->stream ? ' / '.$student->stream->name : '' }}</div></div>
                            <div class="col-sm-4"><div class="text-secondary small">{{ __('Academic Year') }}</div><div>{{ $student->academicYear?->name ?? '—' }}</div></div>
                            <div class="col-sm-4"><div class="text-secondary small">{{ __('Gender') }}</div><div>{{ $student->gender ? __(ucfirst($student->gender)) : '—' }}</div></div>
                            <div class="col-sm-4"><div class="text-secondary small">{{ __('Date of birth') }}</div><div>{{ optional($student->date_of_birth)->format('d M Y') ?? '—' }}</div></div>
                        </div>
                        </div>

                        <div class="tab-pane fade" id="studentGuardiansPane{{ $student->id }}">
                        @if ($student->guardians->isEmpty())
                            <p class="text-secondary small">{{ __('No guardian on file.') }}</p>
                        @else
                            @foreach ($student->guardians as $guardian)
                                <div class="border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <div class="fw-semibold">
                                                {{ $guardian->name }}
                                                @if ($guardian->pivot->is_primary)
                                                    <span class="badge badge-status-active rounded-pill px-2 py-1 ms-1">{{ __('Primary') }}</span>
                                                @endif
                                            </div>
                                            <div class="text-secondary small">{{ __(ucfirst($guardian->pivot->relationship)) }}</div>
                                        </div>
                                        <div class="d-flex gap-1 flex-wrap">
                                            @can('guardians.manage')
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#editGuardian{{ $student->id }}_{{ $guardian->id }}">
                                                    <i class="bi bi-pencil"></i> {{ __('Edit') }}
                                                </button>
                                                @if ($guardian->user_id)
                                                    <form method="POST" action="{{ route('school-admin.guardians.account.reset', $guardian) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('{{ __('Reset password') }}?');">
                                                            <i class="bi bi-arrow-repeat"></i> {{ __('Reset password') }}
                                                        </button>
                                                    </form>
                                                @else
                                                    {{-- A plain HTML disclosure, not a nested Bootstrap modal (two modals
                                                         stacked gets fiddly, and this is a couple of fields anyway). Email
                                                         and phone are both optional but at least one is required — phone
                                                         is pre-filled since guardians always have one on file, so "Create"
                                                         works immediately with no typing for the common case. --}}
                                                    <details class="d-inline-block">
                                                        <summary class="btn btn-outline-gold btn-sm d-inline-block" style="list-style: none; cursor: pointer;">
                                                            <i class="bi bi-key"></i> {{ __('Create parent login') }}
                                                        </summary>
                                                        <form method="POST" action="{{ route('school-admin.guardians.account.store', $guardian) }}" class="row g-1 mt-2" style="max-width: 420px;">
                                                            @csrf
                                                            <div class="col-6">
                                                                <input type="email" name="email" class="form-control form-control-sm" placeholder="{{ __('Email (optional)') }}" value="{{ $guardian->email }}">
                                                            </div>
                                                            <div class="col-6">
                                                                <input type="text" name="phone" class="form-control form-control-sm" placeholder="{{ __('Phone (optional)') }}" value="{{ $guardian->phone }}">
                                                            </div>
                                                            <div class="col-12 form-text">{{ __('The parent signs in with whichever of these is filled in — leave one blank to use only the other.') }}</div>
                                                            <div class="col-12">
                                                                <button type="submit" class="btn btn-gold btn-sm">{{ __('Create') }}</button>
                                                            </div>
                                                        </form>
                                                    </details>
                                                @endif
                                            @endcan
                                        </div>
                                    </div>
                                    <div class="row g-2 mt-1 small">
                                        <div class="col-sm-6"><i class="bi bi-telephone text-secondary"></i> {{ $guardian->phone }}</div>
                                        <div class="col-sm-6"><i class="bi bi-envelope text-secondary"></i> {{ $guardian->email ?? '—' }}</div>
                                        @if ($guardian->occupation)
                                            <div class="col-sm-6"><i class="bi bi-briefcase text-secondary"></i> {{ $guardian->occupation }}</div>
                                        @endif
                                        <div class="col-sm-6">
                                            <i class="bi bi-shield-lock text-secondary"></i>
                                            {{ $guardian->user_id ? __('Has a parent login') : __('No login yet') }}
                                        </div>
                                        @if ($guardian->address)
                                            <div class="col-12"><i class="bi bi-geo-alt text-secondary"></i> {{ $guardian->address }}</div>
                                        @endif
                                    </div>

                                    @can('guardians.manage')
                                        <div class="collapse mt-3" id="editGuardian{{ $student->id }}_{{ $guardian->id }}">
                                            <form method="POST" action="{{ route('school-admin.students.guardians.update', [$student, $guardian]) }}" class="border-top pt-3">
                                                @csrf @method('PUT')
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label small">{{ __('Full name') }}</label>
                                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $guardian->name }}" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small">{{ __('Phone') }}</label>
                                                        <input type="text" name="phone" class="form-control form-control-sm" value="{{ $guardian->phone }}" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small">{{ __('Email') }}</label>
                                                        <input type="email" name="email" class="form-control form-control-sm" value="{{ $guardian->email }}">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small">{{ __('Occupation') }}</label>
                                                        <input type="text" name="occupation" class="form-control form-control-sm" value="{{ $guardian->occupation }}">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small">{{ __('Relationship to this student') }}</label>
                                                        <select name="relationship" class="form-select form-select-sm">
                                                            @foreach (['father', 'mother', 'guardian', 'other'] as $rel)
                                                                <option value="{{ $rel }}" @selected($guardian->pivot->relationship === $rel)>{{ __(ucfirst($rel)) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small">{{ __('Language for messages') }}</label>
                                                        <select name="locale" class="form-select form-select-sm">
                                                            <option value="">{{ __('School default') }}</option>
                                                            @foreach (config('app.supported_locales') as $code => $label)
                                                                <option value="{{ $code }}" @selected($guardian->locale === $code)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small">{{ __('Address') }}</label>
                                                        <textarea name="address" class="form-control form-control-sm" rows="2">{{ $guardian->address }}</textarea>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="guardianPrimary{{ $student->id }}_{{ $guardian->id }}" @checked($guardian->pivot->is_primary)>
                                                            <label class="form-check-label small" for="guardianPrimary{{ $student->id }}_{{ $guardian->id }}">{{ __('Primary contact for this student') }}</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <button type="submit" class="btn btn-gold btn-sm">{{ __('Save guardian details') }}</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    @endcan
                                </div>
                            @endforeach
                        @endif
                        </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        @can('students.update')
                            <button type="button" class="btn btn-gold" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editStudentModal{{ $student->id }}">
                                {{ __('Edit') }}
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        @can('students.update')
            <div class="modal fade" id="editStudentModal{{ $student->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.students.update', $student) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">Edit {{ $student->fullName() }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('First name') }}</label>
                                        <input type="text" name="first_name" class="form-control" value="{{ $student->first_name }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Last name') }}</label>
                                        <input type="text" name="last_name" class="form-control" value="{{ $student->last_name }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Gender') }}</label>
                                        <select name="gender" class="form-select">
                                            <option value="male" @selected($student->gender === 'male')>{{ __('Male') }}</option>
                                            <option value="female" @selected($student->gender === 'female')>{{ __('Female') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Date of birth') }}</label>
                                        <input type="date" name="date_of_birth" class="form-control" value="{{ $student->date_of_birth->toDateString() }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Class') }}</label>
                                        <select name="school_class_id" class="form-select">
                                            <option value="">{{ __('Not assigned') }}</option>
                                            @foreach ($classes as $class)
                                                <option value="{{ $class->id }}" @selected($student->school_class_id === $class->id)>{{ $class->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Stream') }}</label>
                                        <select name="stream_id" class="form-select">
                                            <option value="">{{ __('Not assigned') }}</option>
                                            @foreach ($classes as $class)
                                                @foreach ($class->streams as $stream)
                                                    <option value="{{ $stream->id }}" @selected($student->stream_id === $stream->id)>
                                                        {{ $class->name }} — {{ $stream->name }}
                                                    </option>
                                                @endforeach
                                            @endforeach
                                        </select>
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
        @endcan

        @can('students.archive')
            <div class="modal fade" id="archiveStudentModal{{ $student->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.students.archive', $student) }}">
                            @csrf @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title h5">Change Status — {{ $student->fullName() }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-secondary small">{{ __('This keeps the student\'s full history (fees, attendance, exam results) intact — it just marks them as no longer active, rather than deleting the record.') }}</p>
                                <label class="form-label">{{ __('New status') }}</label>
                                <select name="status" class="form-select mb-3" required>
                                    <option value="active" @selected($student->status === 'active')>{{ __('Active') }}</option>
                                    <option value="graduated" @selected($student->status === 'graduated')>{{ __('Graduated') }}</option>
                                    <option value="withdrawn" @selected($student->status === 'withdrawn')>{{ __('Withdrawn') }}</option>
                                    <option value="transferred" @selected($student->status === 'transferred')>{{ __('Transferred') }}</option>
                                    <option value="suspended" @selected($student->status === 'suspended')>{{ __('Suspended') }}</option>
                                </select>
                                <label class="form-label">{{ __('Reason') }} <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="2" minlength="3" maxlength="500" required
                                          placeholder="{{ __('e.g. Transferred to another school, fees unpaid, completed Form 4…') }}"></textarea>

                                @if ($student->statusChanges->isNotEmpty())
                                    <div class="mt-3">
                                        <div class="small text-secondary text-uppercase mb-1">{{ __('Status history') }}</div>
                                        <ul class="list-unstyled small mb-0">
                                            @foreach ($student->statusChanges->take(5) as $change)
                                                <li class="border-top pt-1 mt-1">
                                                    <span class="fw-semibold">{{ ucfirst($change->from_status) }} → {{ ucfirst($change->to_status) }}</span>
                                                    <span class="text-secondary">· {{ $change->created_at->format('d M Y') }}@if ($change->changedBy) · {{ $change->changedBy->name }}@endif</span>
                                                    <div class="text-secondary">{{ $change->reason }}</div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-outline-danger">{{ __('Update Status') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endcan
    @endforeach

    @if (collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'student') || str_starts_with($k, 'guardians')))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new bootstrap.Modal(document.getElementById('admitStudentModal')).show();
            });
        </script>
    @endif

    @if (session('guardian_login_password'))
        <div class="modal fade" id="guardianLoginRevealModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('Parent login ready') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">{{ session('guardian_login_name') }} — {{ session('guardian_login_email') }}</p>
                        <label class="form-label small text-secondary">{{ __('Temporary password') }}</label>
                        <input type="text" class="form-control fw-bold" value="{{ session('guardian_login_password') }}" readonly onclick="this.select();">
                        <div class="form-text">{{ __('It is shown only now — give it to the parent, who should change it after signing in. It was also texted to them if they have a phone number on file.') }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-gold" data-bs-dismiss="modal">{{ __('Done') }}</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new bootstrap.Modal(document.getElementById('guardianLoginRevealModal')).show();
            });
        </script>
    @endif
@endsection
