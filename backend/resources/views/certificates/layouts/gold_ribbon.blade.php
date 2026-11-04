<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#7a1f2b';
        $secondary = $template->secondary_color ?? '#c9a227';
    @endphp
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; }
        .frame { margin: 8mm; box-sizing: border-box; border: 6px double {{ $secondary }}; padding: 24px 50px 20px; text-align: center; }
        .corner { font-size: 16px; color: {{ $secondary }}; }
        .corner-left { float: left; }
        .corner-right { float: right; }
        .school-name { font-size: 20px; font-weight: bold; color: {{ $primary }}; letter-spacing: 1px; text-transform: uppercase; clear: both; }
        .title { font-size: 28px; font-weight: bold; color: {{ $secondary }}; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 2px; }
        .presented { font-size: 12px; color: #5a6478; margin-bottom: 6px; }
        .student-name { font-size: 26px; font-weight: bold; color: {{ $primary }}; margin: 8px 0 18px; }
        .achievement { font-size: 13px; color: #1a1f2b; max-width: 580px; margin: 0 auto 14px; line-height: 1.5; }
        .custom-text { font-size: 10px; color: #5a6478; font-style: italic; margin-top: 6px; }
        .seal {
            width: 62px; height: 62px; border-radius: 50%; border: 3px solid {{ $secondary }};
            display: inline-block; margin: 12px auto 4px; color: {{ $secondary }}; font-size: 22px;
            line-height: 56px; box-sizing: border-box;
        }
        .footer-row { width: 100%; margin-top: 16px; }
        .footer-col { display: inline-block; width: 30%; vertical-align: top; font-size: 11px; color: #5a6478; }
        .cert-number { font-weight: bold; color: {{ $primary }}; }
    </style>
</head>
<body>
    <div class="frame">
        <span class="corner corner-left">&#10022;</span>
        <span class="corner corner-right">&#10022;</span>

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

        <div class="seal">&#10003;</div>

        @if ($template?->custom_text)
            <div class="custom-text">{{ $template->custom_text }}</div>
        @endif

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
