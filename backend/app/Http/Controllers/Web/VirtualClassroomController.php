<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\VirtualClassroom\StoreVirtualClassRequest;
use App\Http\Requests\VirtualClassroom\UpdateVirtualClassRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\VirtualClass;
use App\Services\VirtualClassroomService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VirtualClassroomController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('virtual_classes.view');

        $classes = VirtualClass::query()
            ->with(['schoolClass', 'subject', 'creator'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('scheduled_at')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.virtual-classroom.index', [
            'classes' => $classes,
            'schoolClasses' => SchoolClass::query()->orderBy('name')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreVirtualClassRequest $request, TenantContext $tenant, VirtualClassroomService $classroom)
    {
        $class = $classroom->schedule($tenant->assertBound(), $request->validated(), $request->user());

        return redirect()->route('school-admin.virtual-classroom.index')
            ->with('status', __('":p1" scheduled for :p2.', ['p1' => $class->title, 'p2' => $class->scheduled_at->format('d M Y, H:i')]));
    }

    public function update(UpdateVirtualClassRequest $request, VirtualClass $virtualClass)
    {
        $virtualClass->update($request->validated());

        return redirect()->route('school-admin.virtual-classroom.index')
            ->with('status', __('":p1" updated.', ['p1' => $virtualClass->title]));
    }

    public function destroy(VirtualClass $virtualClass)
    {
        $this->authorize('virtual_classes.manage');

        if ($virtualClass->status !== 'cancelled' && now()->lt($virtualClass->scheduled_at)) {
            return back()->withErrors(['virtual_class' => 'Cancel this session before deleting it.']);
        }

        $virtualClass->delete();

        return redirect()->route('school-admin.virtual-classroom.index')->with('status', 'Session deleted.');
    }

    public function cancel(VirtualClass $virtualClass, VirtualClassroomService $classroom)
    {
        $this->authorize('virtual_classes.manage');

        $classroom->cancel($virtualClass);

        return redirect()->route('school-admin.virtual-classroom.index')->with('status', 'Session cancelled.');
    }

    public function sendInvites(VirtualClass $virtualClass, VirtualClassroomService $classroom)
    {
        $this->authorize('virtual_classes.manage');

        try {
            $result = $classroom->sendInvites($virtualClass, request()->user());
        } catch (ValidationException $e) {
            return redirect()->route('school-admin.virtual-classroom.index')
                ->with('status', $e->validator->errors()->first());
        }

        return redirect()->route('school-admin.virtual-classroom.index')
            ->with('status', __('Invite queued for :p1 guardian(s).', ['p1' => $result['sent']->count()]));
    }
}
