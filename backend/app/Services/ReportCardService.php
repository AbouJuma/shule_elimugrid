<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ReportCardRemark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Builds termly report cards for a class.
 *
 * A subject's term score is the weighted average percentage across every
 * exam in that term (a student's mark / that exam-subject's max marks,
 * weighted by the exam's `weight`; default 1 = plain average). A student's
 * average is the mean of their subject scores, and position is a standard
 * competition ranking on that average (ties share a position). Every query
 * filters by school_id explicitly so this is safe outside an HTTP request.
 */
class ReportCardService
{
    public function __construct(protected GradingService $grading)
    {
    }

    /**
     * @return array{
     *   subjects: Collection, cards: Collection, class_size: int, exams: Collection,
     *   class_average: ?float, scale: Collection
     * }
     */
    public function forClass(School $school, SchoolClass $class, Term $term, bool $publishedOnly = false): array
    {
        $scale = $this->grading->scaleFor($school);

        $exams = Exam::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereHas('classes', fn ($q) => $q->where('school_classes.id', $class->id))
            ->where('term_id', $term->id)
            ->when($publishedOnly, fn ($q) => $q->where('status', 'published'))
            ->with('subjects')
            ->orderBy('id')
            ->get();

        $students = Student::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('school_class_id', $class->id)
            ->where('status', 'active')
            ->with('stream')
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        $marks = ExamMark::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->whereNotNull('marks_obtained')
            ->get()
            ->groupBy('student_id');

        $maxByExamSubject = [];
        $weightByExam = $exams->mapWithKeys(fn ($e) => [$e->id => max((float) $e->weight, 0.0001)])->all();
        $subjects = collect();
        foreach ($exams as $exam) {
            foreach ($exam->subjects as $subject) {
                $maxByExamSubject[$exam->id][$subject->id] = (float) $subject->pivot->max_marks;
                $subjects->put($subject->id, $subject);
            }
        }
        $subjects = $subjects->sortBy('name')->values();

        $remarks = ReportCardRemark::withoutGlobalScopes()
            ->where('school_id', $school->id)->where('term_id', $term->id)->get()->keyBy('student_id');

        $attendance = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereBetween('date', [$term->start_date->toDateString(), $term->end_date->toDateString()])
            // Whole-day rows only — per-subject rows (added alongside this
            // same date) would otherwise inflate the term's attendance count.
            ->whereNull('subject_id')
            ->get()
            ->groupBy('student_id');

        $cards = $students->map(function (Student $student) use ($marks, $maxByExamSubject, $weightByExam, $subjects, $scale, $remarks, $attendance) {
            $studentMarks = $marks->get($student->id, collect());

            $lines = $subjects->map(function ($subject) use ($studentMarks, $maxByExamSubject, $weightByExam, $scale) {
                // The score in each individual exam, for the per-exam breakdown columns.
                $byExam = $studentMarks->where('subject_id', $subject->id)->mapWithKeys(function ($mark) use ($maxByExamSubject, $subject) {
                    $max = $maxByExamSubject[$mark->exam_id][$subject->id] ?? 0;

                    return [$mark->exam_id => $max > 0 ? round((float) $mark->marks_obtained / $max * 100, 1) : null];
                })->all();

                // [percent, weight] per exam the student sat for this subject.
                $scored = $studentMarks->where('subject_id', $subject->id)->map(function ($mark) use ($maxByExamSubject, $weightByExam, $subject) {
                    $max = $maxByExamSubject[$mark->exam_id][$subject->id] ?? 0;

                    return $max > 0 ? [((float) $mark->marks_obtained / $max) * 100, $weightByExam[$mark->exam_id] ?? 1] : null;
                })->filter();

                $weightSum = $scored->sum(fn ($pair) => $pair[1]);
                $percent = $scored->isNotEmpty() ? round($scored->sum(fn ($pair) => $pair[0] * $pair[1]) / $weightSum, 1) : null;
                $grade = $this->grading->gradeFor($scale, $percent);

                return [
                    'subject_id' => $subject->id,
                    'subject' => $subject->name,
                    'percent' => $percent,
                    'by_exam' => $byExam,
                    'grade' => $grade['grade'],
                    'remark' => $grade['remark'],
                ];
            });

            $scored = $lines->whereNotNull('percent');
            $average = $scored->isNotEmpty() ? round($scored->avg('percent'), 1) : null;
            $overall = $this->grading->gradeFor($scale, $average);

            return [
                'student' => $student,
                'lines' => $lines,
                'total' => round($scored->sum('percent'), 1),
                'subjects_taken' => $scored->count(),
                'average' => $average,
                'grade' => $overall['grade'],
                'remark' => $overall['remark'],
                'position' => null,
                'attendance' => $this->attendanceSummary($attendance->get($student->id, collect())),
                'class_teacher_remark' => $remarks->get($student->id)?->class_teacher_remark,
                'head_teacher_remark' => $remarks->get($student->id)?->head_teacher_remark,
            ];
        });

        $cards = $this->rank($cards);

        $subjectStats = $subjects->mapWithKeys(function ($subject) use ($cards) {
            $scores = $cards->map(fn ($c) => $c['lines']->firstWhere('subject_id', $subject->id)['percent'] ?? null)
                ->filter(fn ($p) => $p !== null);

            return [$subject->id => [
                'average' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
                'scores' => $scores->sortDesc()->values(),
            ]];
        });

        $cards = $cards->map(function ($card) use ($subjectStats) {
            $card['lines'] = $card['lines']->map(function ($line) use ($subjectStats) {
                $stats = $subjectStats[$line['subject_id']];
                $line['class_average'] = $stats['average'];
                $line['position'] = $line['percent'] === null
                    ? null
                    : $stats['scores']->filter(fn ($s) => $s > $line['percent'])->count() + 1;

                return $line;
            });

            return $card;
        });

        $averages = $cards->pluck('average')->filter(fn ($a) => $a !== null);

        return [
            'subjects' => $subjects,
            'cards' => $cards->values(),
            'class_size' => $cards->count(),
            'exams' => $exams,
            'class_average' => $averages->isNotEmpty() ? round($averages->avg(), 1) : null,
            'scale' => $scale,
        ];
    }

    /** @return array{present: int, late: int, absent: int, excused: int, total: int, rate: ?float} */
    protected function attendanceSummary(Collection $records): array
    {
        $by = $records->countBy('status');
        $total = $records->count();
        // Present and late both count as attended; excused days are neither.
        $attended = ($by['present'] ?? 0) + ($by['late'] ?? 0);
        $counted = $total - ($by['excused'] ?? 0);

        return [
            'present' => (int) ($by['present'] ?? 0), 'late' => (int) ($by['late'] ?? 0),
            'absent' => (int) ($by['absent'] ?? 0), 'excused' => (int) ($by['excused'] ?? 0),
            'total' => $total,
            'rate' => $counted > 0 ? round($attended / $counted * 100, 1) : null,
        ];
    }

    /** Standard competition ranking (1, 2, 2, 4) on the average, best first. */
    protected function rank(Collection $cards): Collection
    {
        $averages = $cards->pluck('average')->filter(fn ($a) => $a !== null)->sortDesc()->values();

        return $cards->map(function ($card) use ($averages) {
            $card['position'] = $card['average'] === null
                ? null
                : $averages->filter(fn ($a) => $a > $card['average'])->count() + 1;

            return $card;
        });
    }

    public function cardFor(School $school, SchoolClass $class, Term $term, Student $student, bool $publishedOnly = false): ?array
    {
        $report = $this->forClass($school, $class, $term, $publishedOnly);
        $card = $report['cards']->first(fn ($c) => $c['student']->id === $student->id);

        return $card ? [...$report, 'card' => $card] : null;
    }
}
