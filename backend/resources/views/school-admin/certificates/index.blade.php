@extends('layouts.portal')

@section('title', 'Certificates — ElimuGrid')
@section('page-title', __('Certificates'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'certificates'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    @can('certificate_templates.manage')
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>{{ __('Templates') }}</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#newTemplateModal">
                    <i class="bi bi-plus-lg"></i> {{ __('New Template') }}
                </button>
            </div>
            <div class="card-body">
                @if ($templates->isEmpty())
                    <p class="text-secondary small mb-0">{{ __('No templates yet — issued certificates use the built-in default look until you add one.') }}</p>
                @else
                    <div class="row g-3">
                        @foreach ($templates as $template)
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="border rounded p-2 h-100 d-flex flex-column {{ $template->is_default ? 'border-gold border-2' : '' }}">
                                    @include('school-admin.certificates.partials.preview', ['layout' => $template->layout, 'primary' => $template->primary_color, 'secondary' => $template->secondary_color])
                                    <div class="d-flex align-items-center gap-1 mt-2">
                                        <span class="fw-semibold small text-truncate">{{ $template->name }}</span>
                                        @if ($template->is_default)
                                            <span class="badge badge-status-active rounded-pill px-2 py-1">{{ __('Active') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-secondary small mb-2">{{ $layouts[$template->layout]['name'] ?? ucfirst($template->layout) }} · {{ ucfirst($template->type) }}</div>
                                    <div class="d-flex gap-1 mt-auto">
                                        @unless ($template->is_default)
                                            <form method="POST" action="{{ route('school-admin.certificates.templates.activate', $template) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-gold btn-sm py-0">{{ __('Make active') }}</button>
                                            </form>
                                        @endunless
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-bs-toggle="modal" data-bs-target="#editTemplateModal{{ $template->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('school-admin.certificates.templates.destroy', $template) }}"
                                              onsubmit="return confirm('Delete template \'{{ $template->name }}\'?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="valid" @selected(request('status')==='valid')>{{ __('Valid') }}</option>
                    <option value="revoked" @selected(request('status')==='revoked')>{{ __('Revoked') }}</option>
                </select>
            </form>
            <div class="d-flex gap-2">
                @can('certificates.issue')
                    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal">
                        <i class="bi bi-award me-1"></i> {{ __('Issue Certificate') }}
                    </button>
                @endcan
            </div>
        </div>

        @if ($certificates->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No certificates match these filters.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th style="width: 1%;">#</th><th>{{ __('Certificate No.') }}</th><th>{{ __('Student') }}</th><th>{{ __('Title') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($certificates as $certificate)
                        <tr>
                            <td class="text-secondary">{{ $certificates->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $certificate->certificate_number }}</td>
                            <td>{{ $certificate->student->fullName() }}</td>
                            <td>{{ $certificate->title }}</td>
                            <td class="text-secondary small">{{ $certificate->issued_date->format('d M Y') }}</td>
                            <td>
                                <span class="badge {{ $certificate->isValid() ? 'badge-status-active' : 'badge-status-suspended' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($certificate->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('certificates.pdf', $certificate) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}
                                </a>
                                @can('certificates.revoke')
                                    @if ($certificate->isValid())
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#revokeModal{{ $certificate->id }}">
                                            {{ __('Revoke') }}
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $certificates])
        @endif
    </div>

    @can('certificates.revoke')
        @foreach ($certificates as $certificate)
            @if ($certificate->isValid())
                <div class="modal fade" id="revokeModal{{ $certificate->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('school-admin.certificates.revoke', $certificate) }}">
                                @csrf
                                @method('PATCH')
                                <div class="modal-header">
                                    <h2 class="modal-title h5">Revoke {{ $certificate->certificate_number }}</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-secondary small">
                                        {{ __('This certificate will show as revoked to anyone who scans its QR code. This cannot be undone.') }}
                                    </p>
                                    <label class="form-label">{{ __('Reason') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <textarea name="reason" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn btn-outline-danger">{{ __('Revoke Certificate') }}</button>
                                </div>
                            </form>
                    </div>
                </div>
            @endif
        @endforeach
    @endcan

    @can('certificates.issue')
        <div class="modal fade" id="issueModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('school-admin.certificates.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Issue Certificate') }}</h2>
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
                            @if ($templates->isNotEmpty())
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Template') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="certificate_template_id" class="form-select">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">{{ __('Certificate Title') }}</label>
                                <input type="text" name="title" class="form-control" placeholder="{{ __('Certificate of Completion') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Achievement') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <textarea name="achievement" class="form-control" rows="3" placeholder="{{ __('for outstanding performance in...') }}"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Issue Date') }}</label>
                                <input type="date" name="issued_date" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Issue Certificate') }}</button>
                        </div>
                    </form>
            </div>
        </div>
    @endcan

    @can('certificate_templates.manage')
        <div class="modal fade" id="newTemplateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.certificates.templates.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Certificate Template') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label d-block">{{ __('Design') }}</label>
                                <div class="row g-2 layout-gallery">
                                    @foreach ($layouts as $key => $l)
                                        <div class="col-4 col-sm-3">
                                            <label class="d-block">
                                                <input type="radio" class="btn-check layout-radio" name="layout" value="{{ $key }}" data-primary="{{ $l['colors'][0] }}" data-secondary="{{ $l['colors'][1] }}" @checked($key === 'classic')>
                                                <span class="d-block border rounded p-1 layout-thumb" title="{{ __($l['description']) }}">
                                                    @include('school-admin.certificates.partials.preview', ['layout' => $key, 'primary' => $l['colors'][0], 'secondary' => $l['colors'][1]])
                                                    <span class="small d-block text-center text-truncate mt-1">{{ __($l['name']) }}</span>
                                                </span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('Standard Completion') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Type') }}</label>
                                <select name="type" class="form-select">
                                    <option value="completion">{{ __('Completion') }}</option>
                                    <option value="graduation">{{ __('Graduation') }}</option>
                                    <option value="academic">{{ __('Academic') }}</option>
                                    <option value="participation">{{ __('Participation') }}</option>
                                    <option value="achievement">{{ __('Achievement') }}</option>
                                    <option value="attendance">{{ __('Attendance') }}</option>
                                    <option value="custom">{{ __('Custom') }}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label d-block">{{ __('Orientation') }}</label>
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="orientation" id="certOrientLandscape" value="landscape" checked>
                                    <label class="btn btn-outline-secondary btn-sm" for="certOrientLandscape">{{ __('Horizontal') }}</label>
                                    <input type="radio" class="btn-check" name="orientation" id="certOrientPortrait" value="portrait">
                                    <label class="btn btn-outline-secondary btn-sm" for="certOrientPortrait">{{ __('Vertical') }}</label>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Primary Color') }}</label>
                                    <input type="color" name="primary_color" class="form-control form-control-color w-100" value="#0a1f44">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Secondary Color') }}</label>
                                    <input type="color" name="secondary_color" class="form-control form-control-color w-100" value="#d4a017">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Custom Text') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <input type="text" name="custom_text" class="form-control" maxlength="255" placeholder="{{ __('e.g. a signatory line') }}">
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="newCertTemplateDefault">
                                <label class="form-check-label small" for="newCertTemplateDefault">{{ __('Set as default') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create Template') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @foreach ($templates as $template)
            <div class="modal fade" id="editTemplateModal{{ $template->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.certificates.templates.update', $template) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Template') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label d-block">{{ __('Design') }}</label>
                                    <div class="row g-2 layout-gallery">
                                        @foreach ($layouts as $key => $l)
                                            <div class="col-4 col-sm-3">
                                                <label class="d-block">
                                                    <input type="radio" class="btn-check layout-radio" name="layout" value="{{ $key }}" data-primary="{{ $l['colors'][0] }}" data-secondary="{{ $l['colors'][1] }}" @checked($template->layout === $key)>
                                                    <span class="d-block border rounded p-1 layout-thumb" title="{{ __($l['description']) }}">
                                                        @include('school-admin.certificates.partials.preview', ['layout' => $key, 'primary' => $l['colors'][0], 'secondary' => $l['colors'][1]])
                                                        <span class="small d-block text-center text-truncate mt-1">{{ __($l['name']) }}</span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $template->name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Type') }}</label>
                                    <select name="type" class="form-select">
                                        @foreach (['completion', 'graduation', 'academic', 'participation', 'achievement', 'attendance', 'custom'] as $type)
                                            <option value="{{ $type }}" @selected($template->type === $type)>{{ ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label d-block">{{ __('Orientation') }}</label>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="orientation" id="certOrientLandscape{{ $template->id }}" value="landscape" @checked($template->orientation === 'landscape')>
                                        <label class="btn btn-outline-secondary btn-sm" for="certOrientLandscape{{ $template->id }}">{{ __('Horizontal') }}</label>
                                        <input type="radio" class="btn-check" name="orientation" id="certOrientPortrait{{ $template->id }}" value="portrait" @checked($template->orientation === 'portrait')>
                                        <label class="btn btn-outline-secondary btn-sm" for="certOrientPortrait{{ $template->id }}">{{ __('Vertical') }}</label>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Primary Color') }}</label>
                                        <input type="color" name="primary_color" class="form-control form-control-color w-100" value="{{ $template->primary_color }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Secondary Color') }}</label>
                                        <input type="color" name="secondary_color" class="form-control form-control-color w-100" value="{{ $template->secondary_color }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Custom Text') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="text" name="custom_text" class="form-control" maxlength="255" value="{{ $template->custom_text }}">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="editCertTemplateDefault{{ $template->id }}" @checked($template->is_default)>
                                    <label class="form-check-label small" for="editCertTemplateDefault{{ $template->id }}">{{ __('Set as default') }}</label>
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

<style>
    .layout-thumb { cursor: pointer; transition: border-color .15s, box-shadow .15s; }
    .layout-radio:checked + .layout-thumb { border-color: var(--bs-gold-600, #d4a017) !important; border-width: 2px; box-shadow: 0 0 0 1px var(--bs-gold-600, #d4a017); }
</style>
<script>
    document.querySelectorAll('.layout-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var form = radio.closest('form');
            if (!form || !radio.checked) return;
            var primary = form.querySelector('input[name="primary_color"]');
            var secondary = form.querySelector('input[name="secondary_color"]');
            if (primary) primary.value = radio.dataset.primary;
            if (secondary) secondary.value = radio.dataset.secondary;
        });
    });
</script>
@endsection
