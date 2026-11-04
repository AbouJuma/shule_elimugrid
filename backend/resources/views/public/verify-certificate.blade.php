<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Certificate Verification — ElimuGrid') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#0a1f44;">
    <div class="w-100" style="max-width: 480px;">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5 text-center">
                @if (! $certificate)
                    <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                    <h1 class="h4 fw-bold mt-3 mb-2">{{ __('Certificate Not Found') }}</h1>
                    <p class="text-secondary small mb-0">
                        {{ __('This verification link is invalid. If you scanned a QR code, please check with the issuing school.') }}
                    </p>
                @elseif ($certificate->isValid())
                    <i class="bi bi-patch-check-fill text-success" style="font-size: 3rem;"></i>
                    <h1 class="h4 fw-bold mt-3 mb-1">{{ __('Certificate Verified') }}</h1>
                    <p class="text-secondary small mb-4">{{ __('This certificate is authentic and valid.') }}</p>

                    <dl class="row small text-start">
                        <dt class="col-5 text-secondary">{{ __('Certificate No.') }}</dt>
                        <dd class="col-7 fw-semibold">{{ $certificate->certificate_number }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Issued To') }}</dt>
                        <dd class="col-7 fw-semibold">{{ $certificate->student->fullName() }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Certificate') }}</dt>
                        <dd class="col-7">{{ $certificate->title }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Issued By') }}</dt>
                        <dd class="col-7">{{ $certificate->student->school->name }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Issued Date') }}</dt>
                        <dd class="col-7">{{ $certificate->issued_date->format('d M Y') }}</dd>
                    </dl>
                @else
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
                    <h1 class="h4 fw-bold mt-3 mb-1">{{ __('Certificate Revoked') }}</h1>
                    <p class="text-secondary small mb-4">
                        This certificate was revoked on {{ $certificate->revoked_at->format('d M Y') }}
                        and is no longer valid.
                    </p>
                    <dl class="row small text-start">
                        <dt class="col-5 text-secondary">{{ __('Certificate No.') }}</dt>
                        <dd class="col-7 fw-semibold">{{ $certificate->certificate_number }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Issued By') }}</dt>
                        <dd class="col-7">{{ $certificate->student->school->name }}</dd>
                    </dl>
                @endif
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3 mb-0">{{ __('Verified by ElimuGrid') }}</p>
    </div>
</body>
</html>
