<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 26px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1a1f2b; margin: 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; }
        .page-break { page-break-before: always; }

        .brand-table td { vertical-align: middle; }
        .school-name { font-size: 17px; font-weight: bold; color: #0a1f44; text-transform: uppercase; letter-spacing: 0.5px; }
        .school-meta { font-size: 8.5px; color: #5a6478; margin-top: 2px; }
        .motto { font-size: 9px; color: #b8860b; font-style: italic; margin-top: 2px; }

        .banner { background: #0a1f44; color: #ffffff; text-align: center; padding: 7px 0; margin-top: 8px; border-bottom: 3px solid #d4a017; }
        .banner .title { font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .banner .sub { font-size: 9px; color: #e8b923; margin-top: 2px; }

        .info { margin-top: 10px; }
        .info td { padding: 3px 6px; font-size: 10px; }
        .info .label { color: #5a6478; width: 16%; }
        .info .value { font-weight: bold; color: #0a1f44; width: 34%; border-bottom: 1px dotted #b6bccb; }

        .summary { margin-top: 10px; }
        .summary td { width: 25%; text-align: center; padding: 8px 4px; border: 1px solid #d9dde6; background: #f7f8fa; }
        .summary .num { font-size: 17px; font-weight: bold; color: #0a1f44; }
        .summary .cap { font-size: 8px; color: #5a6478; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 2px; }
        .summary .highlight { background: #fbf1d6; border-color: #d4a017; }

        .subjects { margin-top: 12px; }
        .subjects th { background: #0a1f44; color: #ffffff; font-size: 9px; padding: 6px 5px; text-align: center; border: 1px solid #0a1f44; }
        .subjects th.left { text-align: left; }
        .subjects td { padding: 5px; border: 1px solid #d9dde6; text-align: center; font-size: 9.5px; }
        .subjects td.left { text-align: left; font-weight: bold; }
        .subjects tr.alt td { background: #f7f8fa; }
        .subjects .grade { font-weight: bold; color: #0a1f44; }
        .subjects tfoot td { background: #e3e9f5; font-weight: bold; border-color: #b6bccb; }

        .key { margin-top: 8px; font-size: 8.5px; color: #5a6478; }
        .key span { display: inline-block; margin-right: 10px; }

        .remarks { margin-top: 12px; }
        .remarks td { width: 50%; vertical-align: top; padding: 0 4px; }
        .remark-box { border: 1px solid #d9dde6; padding: 6px 8px; min-height: 42px; }
        .remark-box .h { font-size: 8px; text-transform: uppercase; color: #5a6478; letter-spacing: 0.6px; margin-bottom: 3px; }

        .sign { margin-top: 22px; }
        .sign td { width: 33%; text-align: center; font-size: 8.5px; color: #5a6478; padding: 0 12px; }
        .sign .line { border-top: 1px solid #1a1f2b; padding-top: 3px; }

        .footer { margin-top: 12px; text-align: center; font-size: 8px; color: #8a93a6; border-top: 1px solid #e2e5ea; padding-top: 5px; }
    </style>
</head>
<body>
@foreach ($cards as $card)
    @php
        // Per-exam columns only when there are two to five exams — beyond that the table gets too cramped to read.
        $examCols = $exams->count() >= 2 && $exams->count() <= 5 ? $exams->values() : collect();
        $student = $card['student'];
        $logo = $school->logo_path && file_exists(storage_path('app/public/'.$school->logo_path))
            ? storage_path('app/public/'.$school->logo_path) : null;
    @endphp

    <div class="{{ $loop->first ? '' : 'page-break' }}">
        <table class="brand-table">
            <tr>
                <td style="width: 70px;">
                    @if ($logo)<img src="{{ $logo }}" style="height: 60px;">@endif
                </td>
                <td style="text-align: center;">
                    <div class="school-name">{{ $school->name }}</div>
                    <div class="school-meta">
                        {{ collect([$school->address, $school->phone, $school->email])->filter()->join('  |  ') }}
                    </div>
                    @if ($school->motto)<div class="motto">“{{ $school->motto }}”</div>@endif
                </td>
                <td style="width: 70px;"></td>
            </tr>
        </table>

        <div class="banner">
            <div class="title">{{ __('Termly Academic Report') }}</div>
            <div class="sub">{{ $term->name }} · {{ $term->academicYear?->name }}</div>
        </div>

        <table class="info">
            <tr>
                <td class="label">{{ __('Student') }}</td><td class="value">{{ $student->fullName() }}</td>
                <td class="label">{{ __('Admission No.') }}</td><td class="value">{{ $student->admission_number }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Class') }}</td><td class="value">{{ $class->name }}@if ($student->stream) — {{ $student->stream->name }}@endif</td>
                <td class="label">{{ __('Gender') }}</td><td class="value">{{ __(ucfirst($student->gender)) }}</td>
            </tr>
        </table>

        <table class="summary">
            <tr>
                <td><div class="num">{{ $card['total'] }}</div><div class="cap">{{ __('Total marks') }}</div></td>
                <td><div class="num">{{ $card['average'] !== null ? $card['average'].'%' : '—' }}</div><div class="cap">{{ __('Average') }}</div></td>
                <td><div class="num">{{ $card['grade'] }}</div><div class="cap">{{ __('Overall grade') }}</div></td>
                <td class="highlight"><div class="num">{{ $card['position'] ?? '—' }} <span style="font-size: 10px; color: #5a6478;">/ {{ $classSize }}</span></div><div class="cap">{{ __('Position in class') }}</div></td>
            </tr>
        </table>

        <table class="subjects">
            <thead>
                <tr>
                    <th class="left">{{ __('Subject') }}</th>
                    @foreach ($examCols as $exam)<th>{{ $exam->name }}<br><span style="font-size: 7px; font-weight: normal;">×{{ rtrim(rtrim(number_format((float) $exam->weight, 2), '0'), '.') }}</span></th>@endforeach
                    <th>{{ __('Score %') }}</th>
                    <th>{{ __('Grade') }}</th>
                    <th>{{ __('Class avg.') }}</th>
                    <th>{{ __('Position') }}</th>
                    <th class="left">{{ __('Remark') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($card['lines'] as $line)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td class="left">{{ $line['subject'] }}</td>
                        @foreach ($examCols as $exam)<td>{{ $line['by_exam'][$exam->id] ?? '—' }}</td>@endforeach
                        <td>{{ $line['percent'] !== null ? $line['percent'] : '—' }}</td>
                        <td class="grade">{{ $line['grade'] }}</td>
                        <td>{{ $line['class_average'] !== null ? $line['class_average'] : '—' }}</td>
                        <td>{{ $line['position'] ?? '—' }}</td>
                        <td style="text-align: left;">{{ $line['remark'] ? __($line['remark']) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 6 + $examCols->count() }}" style="padding: 14px;">{{ __('No exam results recorded for this term yet.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($card['lines']->isNotEmpty())
                <tfoot>
                    <tr>
                        <td class="left">{{ __('Total / Average') }}</td>
                        @foreach ($examCols as $exam)<td></td>@endforeach
                        <td>{{ $card['total'] }}</td>
                        <td>{{ $card['grade'] }}</td>
                        <td>{{ $classAverage !== null ? $classAverage : '—' }}</td>
                        <td>{{ $card['position'] ?? '—' }}</td>
                        <td style="text-align: left;">{{ $card['remark'] ? __($card['remark']) : '' }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        @if ($card['attendance']['total'] > 0)
            <div class="key" style="margin-top: 10px;">
                <strong>{{ __('Attendance this term') }}:</strong>
                <span>{{ __('Present') }} {{ $card['attendance']['present'] }}</span>
                <span>{{ __('Late') }} {{ $card['attendance']['late'] }}</span>
                <span>{{ __('Absent') }} {{ $card['attendance']['absent'] }}</span>
                <span>{{ __('Excused') }} {{ $card['attendance']['excused'] }}</span>
                @if ($card['attendance']['rate'] !== null)<span><strong>{{ $card['attendance']['rate'] }}%</strong></span>@endif
            </div>
        @endif

        <div class="key">
            <strong>{{ __('Grading key') }}:</strong>
            @foreach ($scale as $row)
                <span>{{ $row->grade }} = {{ $row->min_percent }}%+ @if ($row->remark) ({{ __($row->remark) }})@endif</span>
            @endforeach
        </div>

        <table class="remarks">
            <tr>
                <td>
                    <div class="remark-box"><div class="h">{{ __('Class teacher remark') }}</div>{{ $card['class_teacher_remark'] }}</div>
                </td>
                <td>
                    <div class="remark-box"><div class="h">{{ __('Head teacher remark') }}</div>{{ $card['head_teacher_remark'] }}</div>
                </td>
            </tr>
        </table>

        <table class="sign">
            <tr>
                <td><div class="line">{{ __('Class teacher') }}</div></td>
                <td><div class="line">{{ __('Head teacher') }}</div></td>
                <td><div class="line">{{ __('Parent / Guardian') }}</div></td>
            </tr>
        </table>

        <div class="footer">{{ __('Generated on :date', ['date' => now()->format('d M Y')]) }} · {{ $school->name }}</div>
    </div>
@endforeach
</body>
</html>
