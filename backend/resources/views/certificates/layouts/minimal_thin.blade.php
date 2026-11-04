<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#22262f';
        $secondary = $template->secondary_color ?? '#5a6478';
    @endphp
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; }
        .frame { margin: 12mm 16mm; box-sizing: border-box; padding: 20px 40px; text-align: center; }
        .school-name { font-size: 14px; color: {{ $secondary }}; letter-spacing: 3px; text-transform: uppercase; }
        .rule { width: 60px; border-top: 2px solid {{ $primary }}; margin: 10px auto; }
        .title { font-size: 26px; font-weight: bold; color: {{ $primary }}; margin: 10px 0 20px; letter-spacing: 1px; }
        .presented { font-size: 11px; color: {{ $secondary }}; margin-bottom: 6px; }
        .student-name { font-size: 24px; font-weight: bold; color: {{ $primary }}; margin: 6px 0 20px; }
        .achievement { font-size: 12px; color: #1a1f2b; max-width: 540px; margin: 0 auto 20px; line-height: 1.6; }
        .custom-text { font-size: 10px; color: {{ $secondary }}; margin-top: 8px; }
        .footer-row { width: 100%; margin-top: 22px; }
        .footer-col { display: inline-block; width: 30%; vertical-align: top; font-size: 10px; color: {{ $secondary }}; }
        .cert-number { font-weight: bold; color: {{ $primary }}; }
    </style>
</head>
<body>
    <div class="frame">
        @if ($school->logo_path)
            <img src="{{ storage_path('app/public/'.$school->logo_path) }}" style="height: 50px; margin-bottom: 8px;">
        @endif
        <div class="school-name">{{ $school->name }}</div>
        <div class="rule"></div>

        <div class="title">{{ $certificate->title }}</div>

        <div class="presented">This certificate is presented to</div>
        <div class="student-name">{{ $certificate->student->fullName() }}</div>

        @if ($certificate->achievement)
            <div class="achievement">{{ $certificate->achievement }}</div>
        @endif

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
