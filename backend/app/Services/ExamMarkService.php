<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared between the API and web marks-entry controllers so the
 * max-marks bound check and the "subject must actually be part of this
 * exam" check can't drift between the two entry points.
 */
class ExamMarkService
{
    /**
     * @param  array<int, array{student_id: int, marks_obtained?: ?float, remarks?: ?string}>  $records
     */
    public function record(Exam $exam, Subject $subject, array $records, User $recordedBy): void
    {
        $examSubject = $this->examSubjectOrFail($exam, $subject);
        $maxMarks = $examSubject->pivot->max_marks;

        foreach ($records as $record) {
            if (($record['marks_obtained'] ?? null) !== null && $record['marks_obtained'] > $maxMarks) {
                throw ValidationException::withMessages([
                    'records' => ["Marks cannot exceed {$maxMarks} for {$subject->name}."],
                ]);
            }
        }

        DB::transaction(function () use ($exam, $subject, $records, $recordedBy) {
            foreach ($records as $record) {
                ExamMark::updateOrCreate(
                    [
                        'school_id' => $exam->school_id,
                        'exam_id' => $exam->id,
                        'subject_id' => $subject->id,
                        'student_id' => $record['student_id'],
                    ],
                    [
                        'marks_obtained' => $record['marks_obtained'] ?? null,
                        'remarks' => $record['remarks'] ?? null,
                        'recorded_by' => $recordedBy->id,
                    ]
                );
            }
        });
    }

    /**
     * 404s (not 422/403) when the subject isn't part of the exam, so it
     * reads the same as "this marks sheet doesn't exist" rather than
     * leaking whether the subject exists elsewhere in the school.
     */
    public function examSubjectOrFail(Exam $exam, Subject $subject)
    {
        $examSubject = $exam->subjects()->where('subjects.id', $subject->id)->first();

        if (! $examSubject) {
            abort(404, 'This subject is not part of this exam.');
        }

        return $examSubject;
    }

    /**
     * 404s (not 422/403) when the class isn't one of the exam's — same
     * reasoning as examSubjectOrFail(): this reads as "this marks sheet
     * doesn't exist" rather than leaking whether the class exists
     * elsewhere in the school. An exam can now run across several
     * classes, so entering marks always means picking one of them first.
     */
    public function classOrFail(Exam $exam, ?int $classId): SchoolClass
    {
        $class = $classId ? $exam->classes()->where('school_classes.id', $classId)->first() : null;

        if (! $class) {
            abort(404, 'Select one of this exam\'s classes to enter marks for.');
        }

        return $class;
    }
}
