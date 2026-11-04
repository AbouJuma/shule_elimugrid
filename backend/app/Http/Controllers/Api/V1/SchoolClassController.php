<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\StoreSchoolClassRequest;
use App\Http\Requests\Academics\UpdateSchoolClassRequest;
use App\Http\Resources\SchoolClassResource;
use App\Models\SchoolClass;
use App\Support\Tenancy\TenantContext;

class SchoolClassController extends Controller
{
    public function index()
    {
        $this->authorize('classes.view');

        return SchoolClassResource::collection(
            SchoolClass::query()->with('streams')->orderBy('order')->orderBy('name')->get()
        );
    }

    public function store(StoreSchoolClassRequest $request, TenantContext $tenant)
    {
        $class = SchoolClass::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return response()->json(['data' => new SchoolClassResource($class)], 201);
    }

    public function show(SchoolClass $class)
    {
        $this->authorize('classes.view');

        return new SchoolClassResource($class->load('streams'));
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $class)
    {
        $class->update($request->validated());

        return new SchoolClassResource($class->fresh('streams'));
    }

    public function destroy(SchoolClass $class)
    {
        $this->authorize('classes.manage');

        if ($class->students()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['class' => [__('This class still has students enrolled — move or archive them first.')]]);
        }

        $class->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }}
