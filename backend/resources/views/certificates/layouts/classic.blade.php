<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $primary = $template->primary_color ?? '#0a1f44';
        $secondary = $template->secondary_color ?? '#d4a017';
    @endphp
    <style>
        /*
         * dompdf's CSS engine doesn't reliably support height:100% on
         * nested boxes, and an explicit height that exactly equals the
         * page size leaves zero tolerance for box-model rounding —both
         * silently overflowed onto a second (near-blank) page. Letting
         * height flow from content, with generous but bounded padding,
         * keeps this reliably on one page instead.
         */
        @page { margin: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
        }
        .frame {
            margin: 8mm;
            box-sizing: border-box;
            border: 8px solid {{ $primary }};
            padding: 20px 50px;
            text-align: center;
        }
        .inner-border {
            border: 1px solid {{ $secondary }};
            padding: 20px;
            box-sizing: border-box;
        }
        .school-name {
            font-size: 22px;
            font-weight: bold;
            color: {{ $primary }};
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .title {
            font-size: 30px;
            font-weight: bold;
            color: {{ $secondary }};
            margin: 18px 0 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .presented {
            font-size: 13px;
            color: #5a6478;
            margin-bottom: 6px;
        }
        .student-name {
            font-size: 28px;
            font-weight: bold;
            color: {{ $primary }};
            margin: 10px 0 20px;
            border-bottom: 2px solid {{ $secondary }};
            display: inline-block;
            padding-bottom: 6px;
        }
        .achievement {
            font-size: 14px;
            color: #1a1f2b;
            max-width: 600px;
            margin: 0 auto 18px;
            line-height: 1.5;
        }
        .custom-text {
            font-size: 11px;
            color: #5a6478;
            font-style: italic;
            margin-top: 10px;
        }
        .footer-row {
            width: 100%;
            margin-top: 20px;
        }
        .footer-col {
            display: inline-block;
            width: 30%;
            vertical-align: top;
            font-size: 11px;
            color: #5a6478;
        }
        .cert-number {
            font-weight: bold;
            color: {{ $primary }};
        }
    </style>
</head>
<body>
    <div class="frame">
        <div class="inner-border">
            @if ($school->logo_path)
                <img src="{{ storage_path('app/public/'.$school->logo_path) }}" style="height: 60px; margin-bottom: 10px;">
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
    </div>
</body>
</html>
