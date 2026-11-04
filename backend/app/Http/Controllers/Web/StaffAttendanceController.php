<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\HR\MarkStaffAttendanceRequest;
use App\Models\StaffAttendanceRecord;
use App\Models\User;
use App\Services\StaffAttendanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffAttendanceController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant, StaffAttendanceService $attendance)
    {
        $this->authorize('staff_attendance.view');

        $school = $tenant->assertBound();
        $date = $request->string('date')->isNotEmpty() ? $request->string('date')->toString() : now()->toDateString();

        $staff = User::query()
            ->whereHas('staffProfile')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $existing = StaffAttendanceRecord::where('school_id', $school->id)
            ->where('date', $date)
            ->get()
            ->keyBy('user_id');

        $register = [
            'staff' => $staff->map(fn ($user) => [
                'user' => $user,
                'status' => $existing->get($user->id)?->status,
                'remarks' => $existing->get($user->id)?->remarks,
            ]),
        ];

        return view('school-admin.staff-attendance.index', [
            'date' => $date,
            'register' => $register,
            'lock' => $attendance->lockFor($school, Carbon::parse($date)),
        ]);
    }

    public function store(MarkStaffAttendanceRequest $request, TenantContext $tenant, StaffAttendanceService $attendance)
    {
        $school = $tenant->assertBound();
        $data = $request->validated();

        $attendance->markForSchool($school, Carbon::parse($data['date']), $data['records'], $request->user());

        return redirect()
            ->route('school-admin.staff-attendance.index', ['date' => $data['date']])
            ->with('status', 'Staff attendance saved.');
    }

    public function unlock(Request $request, TenantContext $tenant, StaffAttendanceService $attendance)
    {
        $this->authorize('staff_attendance.unlock');

        $school = $tenant->assertBound();
        $data = $request->validate(['date' => ['required', 'date']]);

        $attendance->unlock($school, Carbon::parse($data['date']));

        return redirect()
            ->route('school-admin.staff-attendance.index', ['date' => $data['date']])
            ->with('status', 'Register unlocked — you can now make changes.');
    }
}
