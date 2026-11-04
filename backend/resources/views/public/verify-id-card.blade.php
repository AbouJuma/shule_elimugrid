<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Student ID Verification — ElimuGrid') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#0a1f44;">
    <div class="w-100" style="max-width: 480px;">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5 text-center">
                @if (! $idCard)
                    <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                    <h1 class="h4 fw-bold mt-3 mb-2">{{ __('ID Card Not Found') }}</h1>
                    <p class="text-secondary small mb-0">
                        {{ __('This verification link is invalid. If you scanned a QR code, please check with the issuing school.') }}
                    </p>
                @elseif ($idCard->isActive())
                    <i class="bi bi-patch-check-fill text-success" style="font-size: 3rem;"></i>
                    <h1 class="h4 fw-bold mt-3 mb-1">{{ __('Valid Student ID') }}</h1>
                    <p class="text-secondary small mb-4">{{ __('This is an active, currently valid student identification card.') }}</p>

                    <dl class="row small text-start">
                        <dt class="col-5 text-secondary">{{ __('Card No.') }}</dt>
                        <dd class="col-7 fw-semibold">{{ $idCard->card_number }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Student') }}</dt>
                        <dd class="col-7 fw-semibold">{{ $idCard->student->fullName() }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Admission No.') }}</dt>
                        <dd class="col-7">{{ $idCard->student->admission_number }}</dd>
                        <dt class="col-5 text-secondary">{{ __('Class') }}</dt>
                        <dd class="col-7">{{ $idCard->student->schoolClass?->name ?? '—' }}</dd>
                        <dt class="col-5 text-secondary">{{ __('School') }}</dt>
                        <dd class="col-7">{{ $idCard->student->school->name }}</dd>
                    </dl>
                @else
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
                    <h1 class="h4 fw-bold mt-3 mb-1">
                        {{ $idCard->status === 'revoked' ? 'ID Card Revoked' : 'ID Card No Longer Valid' }}
                    </h1>
                    <p class="text-secondary small mb-4">
                        @if ($idCard->status === 'revoked')
                            This card was revoked on {{ $idCard->revoked_at->format('d M Y') }} and is no longer valid.
                        @else
                            This card has been replaced by a newer one and is no longer valid.
                        @endif
                    </p>
                    <dl class="row small text-start">
                        <dt class="col-5 text-secondary">{{ __('Card No.') }}</dt>
                        <dd class="col-7 fw-semibold">{{ $idCard->card_number }}</dd>
                        <dt class="col-5 text-secondary">{{ __('School') }}</dt>
                        <dd class="col-7">{{ $idCard->student->school->name }}</dd>
                    </dl>
                @endif
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3 mb-0">{{ __('Verified by ElimuGrid') }}</p>
    </div>
</body>
</html>
