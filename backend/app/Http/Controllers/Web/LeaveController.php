<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\HR\StoreLeaveTypeRequest;
use App\Http\Requests\HR\UpdateLeaveTypeRequest;
use App\Models\LeaveRequest as LeaveRequestModel;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeaveController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $canApprove = $request->user()->can('leave.approve');

        $myRequests = LeaveRequestModel::query()
            ->with('leaveType')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $pendingQueue = $canApprove
            ? LeaveRequestModel::query()->with(['user', 'leaveType'])
                ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(PageSize::resolve($request), ['*'], 'queue_page')
                ->withQueryString()
            : null;

        return view('school-admin.leave.index', [
            'myRequests' => $myRequests,
            'pendingQueue' => $pendingQueue,
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
            'canApprove' => $canApprove,
        ]);
    }

    public function store(Request $request, LeaveService $leave)
    {
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        $leave->apply(
            $request->user(),
            $leaveType,
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
            $data['reason'] ?? null,
        );

        return redirect()->route('school-admin.leave.index')->with('status', 'Leave request submitted.');
    }

    public function approve(LeaveRequestModel $leaveRequest, LeaveService $leave)
    {
        $this->authorize('leave.approve');

        $leave->approve($leaveRequest, request()->user());

        return redirect()->route('school-admin.leave.index')->with('status', 'Leave request approved.');
    }

    public function reject(LeaveRequestModel $leaveRequest, LeaveService $leave)
    {
        $this->authorize('leave.approve');

        $leave->reject($leaveRequest, request()->user());

        return redirect()->route('school-admin.leave.index')->with('status', 'Leave request rejected.');
    }

    public function storeType(StoreLeaveTypeRequest $request, TenantContext $tenant)
    {
        LeaveType::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return redirect()->route('school-admin.leave.index')->with('status', 'Leave type created.');
    }

    public function updateType(UpdateLeaveTypeRequest $request, LeaveType $leaveType)
    {
        $leaveType->update($request->validated());

        return redirect()->route('school-admin.leave.index')->with('status', 'Leave type updated.');
    }

    public function destroyType(LeaveType $leaveType)
    {
        $this->authorize('hr.manage');

        if ($leaveType->leaveRequests()->exists()) {
            return back()->withErrors(['leave_type' => 'This leave type has requests recorded against it — it can\'t be deleted.']);
        }

        $leaveType->delete();

        return redirect()->route('school-admin.leave.index')->with('status', 'Leave type deleted.');
    }
}
