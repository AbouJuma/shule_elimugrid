<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\StoreSubjectRequest;
use App\Http\Requests\Academics\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Support\Tenancy\TenantContext;

class SubjectController extends Controller
{
    public function index()
    {
        $this->authorize('subjects.view');

        return SubjectResource::collection(
            Subject::query()->with('classes')->orderBy('name')->get()
        );
    }

    public function store(StoreSubjectRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        $subject = Subject::create([
            'school_id' => $tenant->id(),
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
        ]);

        if (! empty($data['school_class_ids'])) {
            $subject->classes()->sync($data['school_class_ids']);
        }

        return response()->json(['data' => new SubjectResource($subject->load('classes'))], 201);
    }

    public function update(UpdateSubjectRequest $request, \App\Models\Subject $subject)
    {
        $data = $request->validated();

        $subject->update(['name' => $data['name'], 'code' => $data['code'] ?? null]);

        if (array_key_exists('school_class_ids', $data)) {
            $subject->classes()->sync($data['school_class_ids']);
        }

        return response()->json(['data' => new SubjectResource($subject->fresh('classes'))]);
    }

    public function destroy(\App\Models\Subject $subject)
    {
        $this->authorize('subjects.manage');

        if (\Illuminate\Support\Facades\DB::table('exam_subjects')->where('subject_id', $subject->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['subject' => [__('This subject has exam records — it can\'t be deleted.')]]);
        }

        $subject->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }}
