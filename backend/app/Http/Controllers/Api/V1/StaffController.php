<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreStaffRequest;
use App\Http\Requests\HR\UpdateStaffProfileRequest;
use App\Models\User;
use App\Services\StaffOnboardingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Staff records for the mobile app (same rules and services as the portal's HR page). */
class StaffController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hr.view');

        $staff = User::query()
            ->with(['roles', 'staffProfile'])
            ->whereHas('staffProfile')
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'data' => $staff->getCollection()->map(fn ($u) => $this->resource($u, $request))->values(),
            'meta' => ['current_page' => $staff->currentPage(), 'last_page' => $staff->lastPage(), 'total' => $staff->total()],
            'links' => ['next' => $staff->nextPageUrl()],
        ]);
    }

    public function store(StoreStaffRequest $request, TenantContext $tenant, StaffOnboardingService $onboarding)
    {
        $result = $onboarding->onboard($tenant->assertBound(), $request->validated());
        $user = $result['user']->load(['roles', 'staffProfile']);

        // The temporary password is shown once, to the person creating the account.
        return response()->json(['data' => $this->resource($user, $request), 'temporary_password' => $result['temporary_password']], 201);
    }

    public function update(UpdateStaffProfileRequest $request, User $user)
    {
        abort_unless($user->staffProfile, 404);

        $data = $request->validated();

        if (array_key_exists('role', $data) && $user->id === $request->user()->id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['role' => [__('You cannot change your own role.')]]);
        }

        DB::transaction(function () use ($data, $user) {
            $accountFields = collect($data)->only(['name', 'email', 'phone']);
            if ($accountFields->isNotEmpty()) {
                $user->update($accountFields->all());
            }

            if (array_key_exists('role', $data)) {
                $user->syncRoles([$data['role']]);
            }

            $user->staffProfile->update(collect($data)->except(['name', 'email', 'phone', 'role'])->all());
        });

        return response()->json(['data' => $this->resource($user->fresh(['roles', 'staffProfile']), $request)]);
    }

    public function status(Request $request, User $user)
    {
        $this->authorize('users.suspend');
        abort_unless($user->staffProfile, 404);
        abort_if($user->id === $request->user()->id, 422, __('You cannot suspend your own account.'));

        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $user->update(['status' => $data['status']]);

        return response()->json(['data' => $this->resource($user->fresh(['roles', 'staffProfile']), $request)]);
    }

    protected function resource(User $u, Request $request): array
    {
        $p = $u->staffProfile;

        return [
            'id' => $u->uuid,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'status' => $u->status,
            'roles' => $u->roles->pluck('name')->values(),
            'profile' => $p ? [
                'employee_number' => $p->employee_number,
                'job_title' => $p->job_title,
                'department' => $p->department,
                'employment_type' => $p->employment_type,
                'date_joined' => $p->date_joined?->toDateString(),
                'date_left' => $p->date_left?->toDateString(),
                'national_id' => $p->national_id,
                'address' => $p->address,
                'next_of_kin_name' => $p->next_of_kin_name,
                'next_of_kin_phone' => $p->next_of_kin_phone,
                // Salary is payroll's business, not everyone in HR's.
                'basic_salary' => $request->user()->can('payroll.view') ? (string) $p->basic_salary : null,
            ] : null,
        ];
    }
}
