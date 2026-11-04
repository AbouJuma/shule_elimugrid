<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\StoreAcademicYearRequest;
use App\Http\Requests\Academics\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Support\Tenancy\TenantContext;

class AcademicYearController extends Controller
{
    public function index()
    {
        $this->authorize('academic_years.view');

        return AcademicYearResource::collection(
            AcademicYear::query()->orderByDesc('start_date')->get()
        );
    }

    public function store(StoreAcademicYearRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        if ($data['is_current'] ?? false) {
            AcademicYear::query()->update(['is_current' => false]);
        }

        $year = AcademicYear::create(['school_id' => $tenant->id(), ...$data]);

        return response()->json(['data' => new AcademicYearResource($year)], 201);
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear)
    {
        $data = $request->validated();

        if ($data['is_current'] ?? false) {
            AcademicYear::query()->where('id', '!=', $academicYear->id)->update(['is_current' => false]);
        }

        $academicYear->update($data);

        return new AcademicYearResource($academicYear->fresh());
    }
}
