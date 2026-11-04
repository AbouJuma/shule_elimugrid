<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\RecordExamMarksRequest;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\Subject;
use App\Services\ExamMarkService;
use Illuminate\Http\Request;

class ExamMarkController extends Controller
{
    public function index(Request $request, Exam $exam, Subject $subject, ExamMarkService $service)
    {
        $this->authorize('exams.view');

        $examSubject = $service->examSubjectOrFail($exam, $subject);
        $class = $service->classOrFail($exam, $request->integer('school_class_id'));

        $class->load(['students' => fn ($q) => $q->orderBy('first_name')]);
        $existing = ExamMark::where('exam_id', $exam->id)->where('subject_id', $subject->id)
            ->whereIn('student_id', $class->students->pluck('id'))
            ->get()->keyBy('student_id');

        return response()->json([
            'data' => [
                'exam' => ['id' => $exam->id, 'name' => $exam->name, 'status' => $exam->status],
                'subject' => ['id' => $subject->id, 'name' => $subject->name],
                'school_class' => ['id' => $class->id, 'name' => $class->name],
                'max_marks' => $examSubject->pivot->max_marks,
                'students' => $class->students->map(fn ($student) => [
                    'id' => $student->id,
                    'name' => $student->fullName(),
                    'admission_number' => $student->admission_number,
                    'marks_obtained' => $existing->get($student->id)?->marks_obtained,
                    'remarks' => $existing->get($student->id)?->remarks,
                ]),
            ],
        ]);
    }

    public function store(RecordExamMarksRequest $request, Exam $exam, Subject $subject, ExamMarkService $service)
    {
        $service->record($exam, $subject, $request->validated()['records'], $request->user());

        return response()->json(['message' => 'Marks recorded.']);
    }
}
