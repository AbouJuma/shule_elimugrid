<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreHomeworkRequest;
use App\Models\Homework;
use App\Services\HomeworkService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('homework.view');

        $homeworks = Homework::query()
            ->with(['schoolClass', 'subject'])
            ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->latest()
            ->get();

        return response()->json(['data' => $homeworks->map($this->toArray(...))]);
    }

    public function store(StoreHomeworkRequest $request, TenantContext $tenant, HomeworkService $homework)
    {
        $item = $homework->create($tenant->assertBound(), $request->validated(), $request->file('attachment'), $request->user());

        return response()->json(['data' => $this->toArray($item)], 201);
    }

    public function destroy(Homework $homework, HomeworkService $service)
    {
        $this->authorize('homework.manage');

        $service->delete($homework);

        return response()->json(['data' => ['deleted' => true]]);
    }

    protected function toArray(Homework $homework): array
    {
        return [
            'id' => $homework->uuid,
            'title' => $homework->title,
            'instructions' => $homework->instructions,
            'due_date' => $homework->due_date?->toDateString(),
            'school_class' => $homework->schoolClass ? ['id' => $homework->schoolClass->id, 'name' => $homework->schoolClass->name] : null,
            'subject' => $homework->subject ? ['id' => $homework->subject->id, 'name' => $homework->subject->name] : null,
            'attachment_url' => $homework->attachment_path ? route('school-admin.homework.attachment', $homework) : null,
            'created_at' => $homework->created_at->toIso8601String(),
        ];
    }
}
