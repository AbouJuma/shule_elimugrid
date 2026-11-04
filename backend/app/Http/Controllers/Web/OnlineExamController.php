<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Learning\StoreOnlineExamRequest;
use App\Models\OnlineExam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\OnlineExamService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class OnlineExamController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('online_exams.view');

        return view('school-admin.online-exams.index', [
            'exams' => OnlineExam::query()
                ->with(['schoolClass', 'subject', 'creator'])
                ->withCount('questions')
                ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'classes' => SchoolClass::query()->orderBy('name')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreOnlineExamRequest $request, TenantContext $tenant, OnlineExamService $exams)
    {
        $exam = $exams->create($tenant->assertBound(), $request->validated(), $request->user());

        return redirect()->route('school-admin.online-exams.show', $exam)
            ->with('status', __('Online exam ":p1" created as a draft — publish it once you\'re happy with it.', ['p1' => $exam->title]));
    }

    public function show(OnlineExam $exam)
    {
        $this->authorize('online_exams.view');

        $exam->load(['questions.choices', 'schoolClass', 'subject']);

        $attempts = $exam->attempts()
            ->with('student')
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();

        return view('school-admin.online-exams.show', ['exam' => $exam, 'attempts' => $attempts]);
    }

    public function publish(OnlineExam $exam, OnlineExamService $exams)
    {
        $this->authorize('online_exams.manage');

        $exams->publish($exam);

        return redirect()->route('school-admin.online-exams.show', $exam)->with('status', 'Exam published — visible to students now.');
    }

    public function destroy(OnlineExam $exam)
    {
        $this->authorize('online_exams.manage');

        $exam->delete();

        return redirect()->route('school-admin.online-exams.index')->with('status', 'Exam deleted.');
    }
}
