<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\HR\StoreStaffRequest;
use App\Http\Requests\HR\UpdateStaffProfileRequest;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffOnboardingService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class StaffController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $this->authorize('hr.view');

        $staff = User::query()
            ->with(['roles', 'staffProfile'])
            ->whereHas('staffProfile')
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.staff.index', [
            'staff' => $staff,
            'roles' => Role::query()->where('school_id', $tenant->assertBound()->id)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStaffRequest $request, TenantContext $tenant, StaffOnboardingService $onboarding)
    {
        $result = $onboarding->onboard($tenant->assertBound(), $request->validated());

        return redirect()->route('school-admin.staff.index')->with(
            'status',
            "{$result['user']->name} onboarded — employee number {$result['user']->staffProfile->employee_number}. ".
            "Temporary password: {$result['temporary_password']}"
        );
    }

    public function updateProfile(UpdateStaffProfileRequest $request, StaffProfile $staffProfile)
    {
        $data = $request->validated();
        $user = $staffProfile->user;

        if (array_key_exists('role', $data) && $user->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $user, $staffProfile) {
            $accountFields = collect($data)->only(['name', 'email', 'phone']);
            if ($accountFields->isNotEmpty()) {
                $user->update($accountFields->all());
            }

            if (array_key_exists('role', $data)) {
                $user->syncRoles([$data['role']]);
            }

            $staffProfile->update(collect($data)->except(['name', 'email', 'phone', 'role'])->all());
        });

        return redirect()->route('school-admin.staff.index')->with('status', 'Staff profile updated.');
    }

    public function toggleStatus(User $user)
    {
        $this->authorize('users.suspend');

        $user->update(['status' => $user->status === 'active' ? 'suspended' : 'active']);

        return redirect()->route('school-admin.staff.index')
            ->with('status', __(':p1 is now :p2.', ['p1' => $user->name, 'p2' => __($user->status)]));
    }

    /**
     * A small QR encoding the staff member's employee number — printable
     * or screenable for the gate scanner (AttendanceScanService looks a
     * scanned code up by employee_number first).
     */
    public function qr(User $user)
    {
        $this->authorize('hr.view');

        abort_unless($user->staffProfile, 404);

        $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($user->staffProfile->employee_number);

        return response($svg)->header('Content-Type', 'image/svg+xml');
    }
}
