<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\AddExamSubjectRequest;
use App\Http\Requests\Academics\StoreExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('exams.view');

        return ExamResource::collection(
            Exam::query()
                ->with(['classes', 'academicYear'])
                ->when($request->integer('school_class_id'), fn ($q, $id) => $q->whereHas('classes', fn ($q) => $q->where('school_classes.id', $id)))
                ->latest()
                ->get()
        );
    }

    public function store(StoreExamRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();
        $classIds = $data['school_class_ids'];
        unset($data['school_class_ids']);

        $exam = Exam::create(['school_id' => $tenant->id(), ...$data]);
        $exam->classes()->attach($classIds);

        return response()->json(['data' => new ExamResource($exam->load('classes'))], 201);
    }

    public function show(Exam $exam)
    {
        $this->authorize('exams.view');

        return new ExamResource($exam->load(['classes', 'academicYear', 'subjects']));
    }

    public function addSubject(AddExamSubjectRequest $request, Exam $exam)
    {
        $data = $request->validated();

        $exam->subjects()->syncWithoutDetaching([
            $data['subject_id'] => ['max_marks' => $data['max_marks']],
        ]);

        return new ExamResource($exam->load('subjects'));
    }

    public function publish(Exam $exam)
    {
        $this->authorize('exams.publish');

        $exam->update(['status' => 'published']);

        return new ExamResource($exam->fresh());
    }
}
