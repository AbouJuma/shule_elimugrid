<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\StoreFeeStructureRequest;
use App\Http\Requests\Fees\UpdateFeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Models\FeeStructure;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class FeeStructureController extends Controller
{
    public function index()
    {
        $this->authorize('fees.view');

        return FeeStructureResource::collection(
            FeeStructure::query()->with(['schoolClass', 'academicYear', 'term', 'items'])->latest()->get()
        );
    }

    public function store(StoreFeeStructureRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        $feeStructure = DB::transaction(function () use ($data, $tenant) {
            $feeStructure = FeeStructure::create([
                'school_id' => $tenant->id(),
                'academic_year_id' => $data['academic_year_id'],
                'school_class_id' => $data['school_class_id'],
                'term_id' => $data['term_id'] ?? null,
                'name' => $data['name'],
            ]);

            foreach ($data['items'] as $item) {
                $feeStructure->items()->create($item);
            }

            return $feeStructure;
        });

        return response()->json([
            'data' => new FeeStructureResource($feeStructure->load(['schoolClass', 'academicYear', 'term', 'items'])),
        ], 201);
    }

    public function show(FeeStructure $feeStructure)
    {
        $this->authorize('fees.view');

        return new FeeStructureResource($feeStructure->load(['schoolClass', 'academicYear', 'term', 'items']));
    }

    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $feeStructure) {
            $feeStructure->update(['name' => $data['name']]);
            $feeStructure->items()->delete();

            foreach ($data['items'] as $item) {
                $feeStructure->items()->create($item);
            }
        });

        return new FeeStructureResource($feeStructure->fresh(['schoolClass', 'academicYear', 'term', 'items']));
    }

    public function destroy(FeeStructure $feeStructure)
    {
        $this->authorize('fees.manage');

        if (\App\Models\Invoice::where('fee_structure_id', $feeStructure->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fee_structure' => [__('Invoices have already been generated from this fee structure — it can\'t be deleted.')]]);
        }

        DB::transaction(function () use ($feeStructure) {
            $feeStructure->items()->delete();
            $feeStructure->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }}
