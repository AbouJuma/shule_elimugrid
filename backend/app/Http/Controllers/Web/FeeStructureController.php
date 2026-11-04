<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Fees\StoreFeeStructureRequest;
use App\Http\Requests\Fees\UpdateFeeStructureRequest;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeStructureController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('fees.view');

        return view('school-admin.fee-structures.index', [
            'structures' => FeeStructure::query()
                ->with(['schoolClass', 'academicYear', 'items'])
                ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'classes' => SchoolClass::query()->orderBy('name')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
        ]);
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

        return redirect()->route('school-admin.fee-structures.index')->with('status', __('Fee structure ":p1" created.', ['p1' => $feeStructure->name]));
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

        return redirect()->route('school-admin.fee-structures.index')->with('status', __('Fee structure ":p1" updated.', ['p1' => $feeStructure->name]));
    }

    public function destroy(FeeStructure $feeStructure)
    {
        $this->authorize('fees.manage');

        if (Invoice::where('fee_structure_id', $feeStructure->id)->exists()) {
            return back()->withErrors(['fee_structure' => 'Invoices have already been generated from this fee structure — it can\'t be deleted.']);
        }

        DB::transaction(function () use ($feeStructure) {
            $feeStructure->items()->delete();
            $feeStructure->delete();
        });

        return redirect()->route('school-admin.fee-structures.index')->with('status', __('Fee structure ":p1" deleted.', ['p1' => $feeStructure->name]));
    }
}
