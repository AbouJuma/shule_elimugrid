@extends('layouts.portal')

@section('title', __('School Profile').' — ElimuGrid')
@section('page-title', __('School Profile'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'school-settings'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white">{{ __('School logo') }}</div>
                <div class="card-body text-center">
                    @if ($school->logo_path)
                        <img src="{{ route('school.logo', ['v' => $school->updated_at?->timestamp]) }}" alt="Logo" class="img-fluid rounded border p-2 mb-3" style="max-height: 160px;">
                    @else
                        <div class="rounded border d-flex align-items-center justify-content-center text-secondary mb-3" style="height: 160px;">
                            <div><i class="bi bi-image display-5 d-block"></i>{{ __('No logo yet') }}</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('school-admin.settings.school.logo') }}" enctype="multipart/form-data" class="text-start">
                        @csrf
                        <label class="form-label small">{{ __('Upload a new logo') }}</label>
                        <input type="file" name="logo" class="form-control mb-2" accept="image/png,image/jpeg,image/webp" required>
                        <div class="form-text mb-2">{{ __('PNG, JPG or WebP, up to 2 MB. Shown on the portal, report cards and certificates.') }}</div>
                        <button type="submit" class="btn btn-gold btn-sm w-100">{{ __('Upload logo') }}</button>
                    </form>

                    @if ($school->logo_path)
                        <form method="POST" action="{{ route('school-admin.settings.school.logo.remove') }}" class="mt-2" onsubmit="return confirm('{{ __('Remove the logo?') }}');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">{{ __('Remove logo') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">{{ __('School details') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('school-admin.settings.school.update') }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">{{ __('School name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $school->name) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Registration no.') }}</label>
                                <input type="text" name="registration_number" class="form-control" value="{{ old('registration_number', $school->registration_number) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Motto') }}</label>
                                <input type="text" name="motto" class="form-control" value="{{ old('motto', $school->motto) }}" placeholder="{{ __('Knowledge is power') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $school->email) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $school->phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Website') }}</label>
                                <input type="url" name="website" class="form-control" value="{{ old('website', $school->website) }}" placeholder="{{ __('https://') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Time zone') }}</label>
                                <select name="timezone" class="form-select" required>
                                    @foreach ($timezones as $tz)
                                        <option value="{{ $tz }}" @selected(old('timezone', $school->timezone) === $tz)>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Language for messages to parents') }}</label>
                                <select name="message_locale" class="form-select" required>
                                    @foreach (config('app.supported_locales') as $code => $label)
                                        <option value="{{ $code }}" @selected(old('message_locale', $school->settings['message_locale'] ?? config('app.locale')) === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('A parent can have their own language on their guardian record.') }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Address') }}</label>
                                <textarea name="address" class="form-control" rows="2">{{ old('address', $school->address) }}</textarea>
                            </div>
                        </div>
                        <div class="mt-4"><button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
