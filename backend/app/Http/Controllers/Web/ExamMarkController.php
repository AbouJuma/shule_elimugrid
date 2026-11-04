<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Academics\RecordExamMarksRequest;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\Subject;
use App\Services\ExamMarkService;
use Illuminate\Http\Request;

class ExamMarkController extends \App\Http\Controllers\Controller
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

        return view('school-admin.exams.marks', [
            'exam' => $exam,
            'subject' => $subject,
            'class' => $class,
            'maxMarks' => $examSubject->pivot->max_marks,
            'rows' => $class->students->map(fn ($student) => [
                'student' => $student,
                'marks_obtained' => $existing->get($student->id)?->marks_obtained,
                'remarks' => $existing->get($student->id)?->remarks,
            ]),
        ]);
    }

    public function store(RecordExamMarksRequest $request, Exam $exam, Subject $subject, ExamMarkService $service)
    {
        $data = $request->validated();

        $service->record($exam, $subject, $data['records'], $request->user());

        return redirect()
            ->route('school-admin.exams.marks.index', [$exam, $subject, 'school_class_id' => $data['school_class_id']])
            ->with('status', 'Marks saved.');
    }
}
