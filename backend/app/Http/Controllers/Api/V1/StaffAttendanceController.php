<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\MarkStaffAttendanceRequest;
use App\Models\StaffAttendanceRecord;
use App\Models\User;
use App\Services\StaffAttendanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffAttendanceController extends Controller
{
    public function index(Request $request, TenantContext $tenant, StaffAttendanceService $attendance)
    {
        $this->authorize('staff_attendance.view');

        $school = $tenant->assertBound();
        $date = Carbon::parse($request->string('date')->isNotEmpty() ? $request->string('date') : now())->toDateString();

        $staff = User::query()->whereHas('staffProfile')->where('status', 'active')->orderBy('name')->get();

        $existing = StaffAttendanceRecord::where('school_id', $school->id)->where('date', $date)->get()->keyBy('user_id');
        $lock = $attendance->lockFor($school, Carbon::parse($date));

        return response()->json([
            'data' => [
                'date' => $date,
                'locked' => $lock !== null,
                'locked_by' => $lock?->lockedBy?->name,
                'can_unlock' => $request->user()->can('staff_attendance.unlock'),
                'staff' => $staff->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_number' => $user->staffProfile?->employee_number,
                    'job_title' => $user->staffProfile?->job_title,
                    'status' => $existing->get($user->id)?->status,
                    'remarks' => $existing->get($user->id)?->remarks,
                ]),
            ],
        ]);
    }

    public function store(MarkStaffAttendanceRequest $request, TenantContext $tenant, StaffAttendanceService $attendance)
    {
        $school = $tenant->assertBound();
        $data = $request->validated();

        $attendance->markForSchool($school, Carbon::parse($data['date']), $data['records'], $request->user());

        return response()->json(['message' => 'Staff attendance recorded.']);
    }

    public function unlock(Request $request, TenantContext $tenant, StaffAttendanceService $attendance)
    {
        $this->authorize('staff_attendance.unlock');

        $school = $tenant->assertBound();
        $data = $request->validate(['date' => ['required', 'date']]);

        $attendance->unlock($school, Carbon::parse($data['date']));

        return response()->json(['message' => 'Register unlocked.']);
    }
}
