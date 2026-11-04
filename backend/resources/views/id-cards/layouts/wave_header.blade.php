<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#0f5c9e';
        $secondary = $template->secondary_color ?? '#f2b705';
        $photoPosition = $template->photo_position ?? 'left';
    @endphp
    <style>
        {{-- Same one-page rule as the classic layout: no height:100% combined with a border. --}}
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; }
        .card { width: 100%; box-sizing: border-box; border: 1px solid {{ $secondary }}; }
        .band {
            background: {{ $primary }};
            padding: 6px 10px;
        }
        .school-name { font-size: 9px; font-weight: bold; color: #ffffff; text-transform: uppercase; }
        .card-label { font-size: 6px; color: {{ $secondary }}; letter-spacing: 1px; margin-top: 1px; }
        .body-row { width: 100%; padding: 8px 10px; box-sizing: border-box; @if ($photoPosition === 'center') text-align: center; @endif }
        .photo-box {
            width: 48px; height: 53px; border: 1px solid {{ $secondary }}; background: #f7f8fa;
            display: inline-block; vertical-align: top; text-align: center; font-size: 6px; color: #5a6478;
            padding-top: 21px; box-sizing: border-box;
            @if ($photoPosition === 'right') float: right; @endif
        }
        .details {
            display: inline-block; vertical-align: top; padding-left: 8px; width: 138px;
            @if ($photoPosition === 'right') padding-left: 0; padding-right: 8px; text-align: right;
            @elseif ($photoPosition === 'center') display: block; width: auto; padding-left: 0; margin-top: 4px; @endif
        }
        .student-name { font-size: 10px; font-weight: bold; color: {{ $primary }}; }
        .detail-line { font-size: 7px; color: #1a1f2b; margin-top: 2px; }
        .detail-label { color: #5a6478; }
        .custom-text { font-size: 6px; color: #5a6478; margin-top: 4px; font-style: italic; }
        .footer { padding: 4px 10px 6px; border-top: 1px solid #e2e5ea; }
        .footer-left { display: inline-block; width: 60%; vertical-align: middle; font-size: 6.5px; color: #5a6478; }
        .footer-right { display: inline-block; width: 30%; vertical-align: middle; text-align: right; }
    </style>
</head>
<body>
    <div class="card">
        <div class="band">
            <div class="school-name">{{ $school->name }}</div>
            <div class="card-label">STUDENT IDENTIFICATION CARD</div>
        </div>

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
            <div class="custom-text" style="padding: 0 10px 4px;">{{ $template->custom_text }}</div>
        @endif

        <div class="footer">
            <div class="footer-left">Issued {{ $idCard->issued_date->format('d M Y') }}</div>
            <div class="footer-right">{!! $qrSvg !!}</div>
        </div>
    </div>
</body>
</html>
