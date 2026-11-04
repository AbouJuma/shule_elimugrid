<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreSchoolRequest;
use App\Models\School;
use App\Services\SchoolOnboardingService;

class SchoolController extends Controller
{
    public function store(StoreSchoolRequest $request, SchoolOnboardingService $onboarding)
    {
        $result = $onboarding->onboard($request->validated());

        return redirect()
            ->route('super-admin.dashboard')
            ->with('onboarded', [
                'school' => $result['school']->name,
                'admin_email' => $result['admin']->email,
                'temporary_password' => $result['temporary_password'],
            ]);
    }

    public function suspend(School $school)
    {
        $this->authorize('schools.suspend');

        $school->update(['status' => 'suspended']);

        return redirect()->route('super-admin.dashboard')->with('status', __(':p1 suspended — nobody there can sign in until it is reactivated.', ['p1' => $school->name]));
    }

    public function activate(School $school)
    {
        $this->authorize('schools.activate');

        $school->update(['status' => 'active']);

        return redirect()->route('super-admin.dashboard')->with('status', __(':p1 is now active.', ['p1' => $school->name]));
    }
}
