<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#1b3a2b';
        $secondary = $template->secondary_color ?? '#c9a227';
    @endphp
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Serif', serif; margin: 0; padding: 0; }
        .frame { margin: 10mm; box-sizing: border-box; border: 1px solid {{ $secondary }}; padding: 26px 55px 22px; text-align: center; }
        .inner-rule { border-top: 1px solid {{ $secondary }}; border-bottom: 1px solid {{ $secondary }}; padding: 16px 0; }
        .school-name { font-size: 19px; font-weight: bold; color: {{ $primary }}; letter-spacing: 2px; text-transform: uppercase; }
        .title { font-size: 26px; font-weight: bold; color: {{ $primary }}; margin: 16px 0 8px; letter-spacing: 1px; }
        .presented { font-size: 12px; color: #5a6478; font-style: italic; margin-bottom: 8px; }
        .student-name { font-size: 25px; font-weight: bold; color: {{ $secondary }}; margin: 8px 0 16px; }
        .achievement { font-size: 13px; color: #1a1f2b; max-width: 560px; margin: 0 auto 14px; line-height: 1.6; }
        .custom-text { font-size: 10px; color: #5a6478; font-style: italic; margin-top: 8px; }
        .seal {
            width: 54px; height: 54px; border-radius: 50%; border: 2px solid {{ $secondary }};
            display: inline-block; margin: 10px auto 2px; color: {{ $secondary }}; font-size: 20px;
            line-height: 50px; box-sizing: border-box;
        }
        .footer-row { width: 100%; margin-top: 14px; }
        .footer-col { display: inline-block; width: 30%; vertical-align: top; font-size: 11px; color: #5a6478; }
        .cert-number { font-weight: bold; color: {{ $primary }}; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="inner-rule">
            @if ($school->logo_path)
                <img src="{{ storage_path('app/public/'.$school->logo_path) }}" style="height: 55px; margin-bottom: 8px;">
            @endif
            <div class="school-name">{{ $school->name }}</div>

            <div class="title">{{ $certificate->title }}</div>

            <div class="presented">This certificate is proudly presented to</div>
            <div class="student-name">{{ $certificate->student->fullName() }}</div>

            @if ($certificate->achievement)
                <div class="achievement">{{ $certificate->achievement }}</div>
            @endif

            <div class="seal">&#10022;</div>

            @if ($template?->custom_text)
                <div class="custom-text">{{ $template->custom_text }}</div>
            @endif
        </div>

        <table class="footer-row">
            <tr>
                <td class="footer-col" style="text-align: left;">
                    Certificate No.<br>
                    <span class="cert-number">{{ $certificate->certificate_number }}</span>
                </td>
                <td class="footer-col" style="text-align: center;">
                    Issued<br>
                    <span class="cert-number">{{ $certificate->issued_date->format('d M Y') }}</span>
                </td>
                <td class="footer-col" style="text-align: right;">
                    {!! $qrSvg !!}
                    <div style="font-size: 8px; margin-top: 4px;">Scan to verify</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
