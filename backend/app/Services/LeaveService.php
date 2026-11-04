<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /**
     * Rejects a request that would exceed the leave type's annual
     * allowance — checked against days already *approved* this calendar
     * year (pending/rejected requests don't count against the balance).
     */
    public function apply(User $user, LeaveType $leaveType, Carbon $start, Carbon $end, ?string $reason): LeaveRequest
    {
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => ['The end date must be on or after the start date.']]);
        }

        $days = $start->diffInDays($end) + 1;

        $alreadyUsed = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $start->year)
            ->sum('days');

        if ($alreadyUsed + $days > $leaveType->days_per_year) {
            $remaining = max(0, $leaveType->days_per_year - $alreadyUsed);
            throw ValidationException::withMessages([
                'days' => ["This request ({$days} day(s)) exceeds your remaining {$leaveType->name} balance ({$remaining} day(s) left of {$leaveType->days_per_year}/year)."],
            ]);
        }

        return LeaveRequest::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    public function approve(LeaveRequest $request, User $approver): void
    {
        $this->assertPending($request);

        $request->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
    }

    public function reject(LeaveRequest $request, User $approver): void
    {
        $this->assertPending($request);

        $request->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now()]);
    }

    protected function assertPending(LeaveRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only pending requests can be actioned.']]);
        }
    }
}
