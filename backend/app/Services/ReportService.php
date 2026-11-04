<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Pure aggregation over data every other module already owns — no new
 * domain modeling, which is exactly why this was picked as the second
 * "uncovered module" to build (after Communications). Every method
 * returns a plain array/Collection the controller hands straight to a
 * Blade view or a CSV writer; there is no persisted "report" concept.
 */
class ReportService
{
    public function enrollment(School $school): Collection
    {
        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->withCount([
                'students as total_count',
                'students as male_count' => fn ($q) => $q->where('gender', 'male'),
                'students as female_count' => fn ($q) => $q->where('gender', 'female'),
                'students as active_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($class) => [
                'class' => $class->name,
                'total' => $class->total_count,
                'male' => $class->male_count,
                'female' => $class->female_count,
                'active' => $class->active_count,
            ]);
    }

    public function financial(School $school, ?string $from, ?string $to): Collection
    {
        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->get()
            ->map(function ($class) use ($school, $from, $to) {
                $invoices = Invoice::query()
                    ->where('school_id', $school->id)
                    ->whereHas('student', fn ($q) => $q->where('school_class_id', $class->id))
                    ->when($from, fn ($q) => $q->whereDate('issued_date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('issued_date', '<=', $to))
                    ->get();

                $invoiced = $invoices->sum('total_amount');
                $collected = $invoices->sum('amount_paid');

                return [
                    'class' => $class->name,
                    'invoiced' => $invoiced,
                    'collected' => $collected,
                    'outstanding' => bcsub((string) $invoiced, (string) $collected, 2),
                    'collection_rate' => $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : null,
                ];
            });
    }

    public function attendance(School $school, ?string $from, ?string $to): Collection
    {
        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->get()
            ->map(function ($class) use ($school, $from, $to) {
                $records = AttendanceRecord::query()
                    ->where('school_id', $school->id)
                    ->where('school_class_id', $class->id)
                    // Whole-day rows only — a student can also have
                    // per-subject rows for the same date now.
                    ->whereNull('subject_id')
                    ->when($from, fn ($q) => $q->where('date', '>=', $from))
                    ->when($to, fn ($q) => $q->where('date', '<=', $to))
                    ->get();

                $total = $records->count();
                $present = $records->where('status', 'present')->count();

                return [
                    'class' => $class->name,
                    'total_records' => $total,
                    'present' => $present,
                    'absent' => $records->where('status', 'absent')->count(),
                    'late' => $records->where('status', 'late')->count(),
                    'excused' => $records->where('status', 'excused')->count(),
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : null,
                ];
            });
    }

    /**
     * Per-subject breakdown for one exam. "Pass" is >=50% of that
     * subject's max_marks — a fixed default, not (yet) a per-school
     * configurable pass mark; documented here since it's a real policy
     * choice, not an arbitrary implementation detail.
     */
    public function academic(Exam $exam): Collection
    {
        $exam->load(['subjects', 'marks']);

        return $exam->subjects->map(function ($subject) use ($exam) {
            $maxMarks = (float) $subject->pivot->max_marks;
            $marks = $exam->marks->where('subject_id', $subject->id)->whereNotNull('marks_obtained');

            $scores = $marks->map(fn ($m) => (float) $m->marks_obtained);
            $passMark = $maxMarks * 0.5;

            return [
                'subject' => $subject->name,
                'max_marks' => $maxMarks,
                'entries' => $scores->count(),
                'average' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
                'highest' => $scores->isNotEmpty() ? $scores->max() : null,
                'lowest' => $scores->isNotEmpty() ? $scores->min() : null,
                'pass_rate' => $scores->isNotEmpty()
                    ? round(($scores->filter(fn ($s) => $s >= $passMark)->count() / $scores->count()) * 100, 1)
                    : null,
            ];
        });
    }
}
