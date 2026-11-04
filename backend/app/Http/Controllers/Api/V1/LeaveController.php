<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Leave for the mobile app: anyone can apply and see their own requests
 * (a floor capability, no permission); people holding leave.approve also
 * get the school's pending queue and can approve/reject.
 */
class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canApprove = $user->can('leave.approve');

        $mine = LeaveRequest::query()->with('leaveType')->where('user_id', $user->id)->latest()->get();

        return response()->json(['data' => [
            'types' => LeaveType::query()->orderBy('name')->get()->map(fn ($t) => [
                'id' => $t->uuid, 'name' => $t->name, 'days_per_year' => $t->days_per_year, 'is_paid' => (bool) $t->is_paid,
            ])->values(),
            'my_requests' => $mine->map(fn ($r) => $this->resource($r))->values(),
            'can_approve' => $canApprove,
            'pending' => $canApprove
                ? LeaveRequest::query()->with(['leaveType', 'user'])->where('status', 'pending')->latest()->get()
                    ->map(fn ($r) => $this->resource($r, withUser: true))->values()
                : [],
        ]]);
    }

    public function store(Request $request, LeaveService $leave)
    {
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'uuid')->where('school_id', $request->user()->school_id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = LeaveType::query()->where('uuid', $data['leave_type_id'])->firstOrFail();

        $created = $leave->apply($request->user(), $type, Carbon::parse($data['start_date']), Carbon::parse($data['end_date']), $data['reason'] ?? null);

        return response()->json(['data' => $this->resource($created->load('leaveType'))], 201);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, LeaveService $leave)
    {
        $this->authorize('leave.approve');
        $leave->approve($leaveRequest, $request->user());

        return response()->json(['data' => $this->resource($leaveRequest->fresh(['leaveType', 'user']), withUser: true)]);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, LeaveService $leave)
    {
        $this->authorize('leave.approve');
        $leave->reject($leaveRequest, $request->user());

        return response()->json(['data' => $this->resource($leaveRequest->fresh(['leaveType', 'user']), withUser: true)]);
    }

    protected function resource(LeaveRequest $r, bool $withUser = false): array
    {
        return [
            'id' => $r->uuid,
            'type' => $r->leaveType?->name,
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'days' => $r->days,
            'reason' => $r->reason,
            'status' => $r->status,
            'requested_by' => $withUser ? $r->user?->name : null,
        ];
    }
}
