<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\StoreGuardianRequest;
use App\Http\Requests\Admissions\UpdateGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('guardians.view');

        $guardians = Guardian::query()
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('phone', 'like', '%'.$request->string('search').'%')
            ))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return GuardianResource::collection($guardians);
    }

    public function store(StoreGuardianRequest $request, TenantContext $tenant)
    {
        $guardian = Guardian::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return response()->json(['data' => new GuardianResource($guardian)], 201);
    }

    public function update(UpdateGuardianRequest $request, Guardian $guardian)
    {
        $guardian->update($request->validated());

        return new GuardianResource($guardian->fresh());
    }
}
