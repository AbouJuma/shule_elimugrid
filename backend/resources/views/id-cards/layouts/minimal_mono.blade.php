<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#22262f';
        $photoPosition = $template->photo_position ?? 'left';
    @endphp
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; }
        .card { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid {{ $primary }}; }
        .school-name { font-size: 8px; font-weight: bold; color: {{ $primary }}; text-transform: uppercase; letter-spacing: 1px; }
        .card-label { font-size: 6px; color: #8a8f9c; margin-top: 1px; }
        .rule { border-top: 1px solid #e2e5ea; margin: 5px 0; }
        .body-row { width: 100%; @if ($photoPosition === 'center') text-align: center; @endif }
        .photo-box {
            width: 48px; height: 53px; border: 1px solid #d7dae0; background: #fafafb;
            display: inline-block; vertical-align: top; text-align: center; font-size: 6px; color: #8a8f9c;
            padding-top: 21px; box-sizing: border-box;
            @if ($photoPosition === 'right') float: right; @endif
        }
        .details {
            display: inline-block; vertical-align: top; padding-left: 10px; width: 134px;
            @if ($photoPosition === 'right') padding-left: 0; padding-right: 10px; text-align: right;
            @elseif ($photoPosition === 'center') display: block; width: auto; padding-left: 0; margin-top: 5px; @endif
        }
        .student-name { font-size: 10px; font-weight: bold; color: {{ $primary }}; }
        .detail-line { font-size: 7px; color: #22262f; margin-top: 2px; }
        .detail-label { color: #8a8f9c; }
        .custom-text { font-size: 6px; color: #8a8f9c; margin-top: 5px; }
        .footer { margin-top: 5px; }
        .footer-left { display: inline-block; width: 60%; vertical-align: middle; font-size: 6.5px; color: #8a8f9c; }
        .footer-right { display: inline-block; width: 30%; vertical-align: middle; text-align: right; }
    </style>
</head>
<body>
    <div class="card">
        <div class="school-name">{{ $school->name }}</div>
        <div class="card-label">STUDENT IDENTIFICATION CARD</div>
        <div class="rule"></div>

        <div class="body-row">
            @if ($photoPosition === 'right')
                <div class="details">
                    <div class="student-name">{{ $idCard->student->fullName() }}</div>
                    <div class="detail-line"><span class="detail-label">Admission No:</span> {{ $idCard->student->admission_number }}</div>
                    <div class="detail-line"><span class="detail-label">Class:</span> {{ $idCard->student->schoolClass?->name ?? '—' }}</div>
                    <div class="detail-line"><span class="detail-label">Card No:</span> {{ $idCard->card_number }}</div>
                </div>
            @endif

            <div class="photo-box">
                @if ($idCard->student->photo_path)
                    <img src="{{ storage_path('app/public/'.$idCard->student->photo_path) }}" style="width: 48px; height: 53px; object-fit: cover;">
                @else
                    No Photo
                @endif
            </div>

            @if ($photoPosition !== 'right')
                <div class="details">
                    <div class="student-name">{{ $idCard->student->fullName() }}</div>
                    <div class="detail-line"><span class="detail-label">Admission No:</span> {{ $idCard->student->admission_number }}</div>
                    <div class="detail-line"><span class="detail-label">Class:</span> {{ $idCard->student->schoolClass?->name ?? '—' }}</div>
                    <div class="detail-line"><span class="detail-label">Card No:</span> {{ $idCard->card_number }}</div>
                </div>
            @endif
        </div>

        @if ($template?->custom_text)
            <div class="custom-text">{{ $template->custom_text }}</div>
        @endif

        <div class="rule"></div>
        <div class="footer">
            <div class="footer-left">Issued {{ $idCard->issued_date->format('d M Y') }}</div>
            <div class="footer-right">{!! $qrSvg !!}</div>
        </div>
    </div>
</body>
</html>
