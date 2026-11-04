<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreSchoolRequest;
use App\Http\Requests\SuperAdmin\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Http\Resources\UserResource;
use App\Models\School;
use App\Services\SchoolOnboardingService;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('schools.view');

        $schools = School::query()
            ->with('subscriptionPackage')
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where(
                'status', $request->string('status')
            ))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return SchoolResource::collection($schools);
    }

    public function store(StoreSchoolRequest $request, SchoolOnboardingService $onboarding)
    {
        $result = $onboarding->onboard($request->validated());

        return response()->json([
            'data' => [
                'school' => new SchoolResource($result['school']),
                'admin' => new UserResource($result['admin']),
                // Returned once, here, so Super Admin can hand it to the
                // school. Automatic delivery (SMS/email) is Phase 6 work —
                // not implemented yet, so this is the only place it appears.
                'temporary_password' => $result['temporary_password'],
            ],
        ], 201);
    }

    public function show(School $school)
    {
        $this->authorize('schools.view');

        return new SchoolResource($school->load('subscriptionPackage'));
    }

    public function update(UpdateSchoolRequest $request, School $school)
    {
        $school->update($request->validated());

        return new SchoolResource($school->fresh('subscriptionPackage'));
    }

    public function suspend(School $school)
    {
        $this->authorize('schools.suspend');

        $school->update(['status' => 'suspended']);

        return new SchoolResource($school);
    }

    public function activate(School $school)
    {
        $this->authorize('schools.activate');

        $school->update(['status' => 'active']);

        return new SchoolResource($school);
    }
}
