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

    /**
     * Soft-deletes the guardian. Any login they had is suspended (not
     * left dangling, able to sign in as a guardian that was just removed)
     * and they're detached from every student first, so a student's own
     * Guardians tab doesn't keep showing a deleted one through a stale
     * pivot row.
     */
    public function destroy(Request $request, Guardian $guardian)
    {
        $this->authorize('guardians.manage');

        if ($guardian->user_id) {
            $guardian->user->update(['status' => 'suspended']);
            $guardian->user->tokens()->delete();
        }

        $guardian->students()->detach();
        $guardian->delete();

        return response()->json(['message' => 'Guardian deleted.']);
    }
}
