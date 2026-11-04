<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#12141c';
        $secondary = $template->secondary_color ?? '#d4a017';
        $photoPosition = $template->photo_position ?? 'left';
    @endphp
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; }
        .card { width: 100%; box-sizing: border-box; padding: 8px 10px; background: {{ $primary }}; border: 1px solid {{ $secondary }}; }
        .school-name { font-size: 8.5px; font-weight: bold; color: #ffffff; text-transform: uppercase; }
        .card-label { font-size: 6.5px; color: {{ $secondary }}; font-weight: bold; letter-spacing: 1px; margin-top: 1px; }
        .rule { border-top: 1px solid {{ $secondary }}; margin: 4px 0; }
        .body-row { width: 100%; @if ($photoPosition === 'center') text-align: center; @endif }
        .photo-box {
            width: 50px; height: 55px; border: 1px solid {{ $secondary }}; background: #22242e;
            display: inline-block; vertical-align: top; text-align: center; font-size: 6px; color: #9099ab;
            padding-top: 22px; box-sizing: border-box;
            @if ($photoPosition === 'right') float: right; @endif
        }
        .details {
            display: inline-block; vertical-align: top; padding-left: 8px; width: 132px;
            @if ($photoPosition === 'right') padding-left: 0; padding-right: 8px; text-align: right;
            @elseif ($photoPosition === 'center') display: block; width: auto; padding-left: 0; margin-top: 4px; @endif
        }
        .student-name { font-size: 10px; font-weight: bold; color: {{ $secondary }}; }
        .detail-line { font-size: 7px; color: #e4e6ec; margin-top: 2px; }
        .detail-label { color: #9099ab; }
        .custom-text { font-size: 6px; color: #9099ab; margin-top: 4px; font-style: italic; }
        .footer { margin-top: 4px; }
        .footer-left { display: inline-block; width: 60%; vertical-align: middle; font-size: 6.5px; color: #9099ab; }
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
                    <img src="{{ storage_path('app/public/'.$idCard->student->photo_path) }}" style="width: 50px; height: 55px; object-fit: cover;">
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

        <div class="footer">
            <div class="footer-left">Issued {{ $idCard->issued_date->format('d M Y') }}</div>
            <div class="footer-right">{!! $qrSvg !!}</div>
        </div>
    </div>
</body>
</html>
