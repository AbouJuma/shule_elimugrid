<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#0f5c9e';
        $secondary = $template->secondary_color ?? '#f2b705';
    @endphp
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; position: relative; }
        {{--
            A colour block cut across the corner, built from plain borders (no CSS transform —
            dompdf does not implement it): a zero-size box whose two visible border edges meet
            at a right angle, positioned over the corner.
        --}}
        .corner-cut {
            position: absolute; top: 0; right: 0; width: 0; height: 0;
            border-style: solid;
            border-width: 130px 130px 0 0;
            border-color: {{ $primary }} transparent transparent transparent;
        }
        .corner-cut-accent {
            position: absolute; top: 0; right: 0; width: 0; height: 0;
            border-style: solid;
            border-width: 96px 96px 0 0;
            border-color: {{ $secondary }} transparent transparent transparent;
        }
        .frame { margin: 8mm; box-sizing: border-box; border: 2px solid {{ $primary }}; padding: 22px 50px; text-align: center; position: relative; }
        .school-name { font-size: 20px; font-weight: bold; color: {{ $primary }}; letter-spacing: 1px; text-transform: uppercase; }
        .title { font-size: 28px; font-weight: bold; color: {{ $secondary }}; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 2px; }
        .presented { font-size: 12px; color: #5a6478; margin-bottom: 6px; }
        .student-name {
            font-size: 26px; font-weight: bold; color: {{ $primary }}; margin: 8px 0 18px;
            border-bottom: 3px solid {{ $secondary }}; display: inline-block; padding-bottom: 6px;
        }
        .achievement { font-size: 13px; color: #1a1f2b; max-width: 580px; margin: 0 auto 14px; line-height: 1.5; }
        .custom-text { font-size: 10px; color: #5a6478; font-style: italic; margin-top: 8px; }
        .footer-row { width: 100%; margin-top: 18px; }
        .footer-col { display: inline-block; width: 30%; vertical-align: top; font-size: 11px; color: #5a6478; }
        .cert-number { font-weight: bold; color: {{ $primary }}; }
    </style>
</head>
<body>
    <div class="corner-cut"></div>
    <div class="corner-cut-accent"></div>

    <div class="frame">
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
