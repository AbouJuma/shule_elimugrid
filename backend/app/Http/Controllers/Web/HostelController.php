<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Hostel\AllocateRoomRequest;
use App\Http\Requests\Hostel\StoreHostelRequest;
use App\Http\Requests\Hostel\StoreHostelRoomRequest;
use App\Http\Requests\Hostel\UpdateHostelRequest;
use App\Http\Requests\Hostel\UpdateHostelRoomRequest;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Models\User;
use App\Services\HostelService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class HostelController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('hostel.view');

        $hostels = Hostel::query()
            ->with(['warden', 'rooms'])
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->orderBy('name')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.hostel.index', [
            'hostels' => $hostels,
            'wardens' => User::query()->role('Warden')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreHostelRequest $request, TenantContext $tenant)
    {
        $hostel = Hostel::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return redirect()->route('school-admin.hostel.index')->with('status', __('Hostel ":p1" added.', ['p1' => $hostel->name]));
    }

    public function update(UpdateHostelRequest $request, Hostel $hostel)
    {
        $hostel->update($request->validated());

        return redirect()->route('school-admin.hostel.index')->with('status', __('Hostel ":p1" updated.', ['p1' => $hostel->name]));
    }

    public function destroy(Hostel $hostel)
    {
        $this->authorize('hostel.manage');

        if ($hostel->rooms()->exists()) {
            return back()->withErrors(['hostel' => 'This hostel still has rooms — delete them first.']);
        }

        $hostel->delete();

        return redirect()->route('school-admin.hostel.index')->with('status', __('Hostel ":p1" deleted.', ['p1' => $hostel->name]));
    }

    public function rooms(Request $request)
    {
        $this->authorize('hostel.view');

        $rooms = HostelRoom::query()
            ->with('hostel')
            ->when($request->integer('hostel_id'), fn ($q, $id) => $q->where('hostel_id', $id))
            ->orderBy('room_number')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.hostel.rooms', [
            'rooms' => $rooms,
            'hostels' => Hostel::query()->orderBy('name')->get(),
            'students' => Student::query()->where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }

    public function storeRoom(StoreHostelRoomRequest $request)
    {
        $data = $request->validated();

        HostelRoom::create(['school_id' => Hostel::findOrFail($data['hostel_id'])->school_id, ...$data]);

        return redirect()->route('school-admin.hostel.rooms')->with('status', __('Room ":p1" added.', ['p1' => $data['room_number']]));
    }

    public function updateRoom(UpdateHostelRoomRequest $request, HostelRoom $room)
    {
        $room->update($request->validated());

        return redirect()->route('school-admin.hostel.rooms')->with('status', __('Room ":p1" updated.', ['p1' => $room->room_number]));
    }

    public function destroyRoom(HostelRoom $room)
    {
        $this->authorize('hostel.manage');

        if ($room->occupied_count > 0) {
            return back()->withErrors(['room' => 'This room still has students allocated — vacate them first.']);
        }

        $room->delete();

        return redirect()->route('school-admin.hostel.rooms')->with('status', __('Room ":p1" deleted.', ['p1' => $room->room_number]));
    }

    public function allocate(AllocateRoomRequest $request, HostelRoom $room, HostelService $hostel)
    {
        $student = Student::findOrFail($request->validated()['student_id']);

        $hostel->allocate($room, $student, $request->user());

        return redirect()->route('school-admin.hostel.rooms')
            ->with('status', __(':p1 allocated to room :p2.', ['p1' => $student->fullName(), 'p2' => $room->room_number]));
    }

    public function allocations(Request $request)
    {
        $this->authorize('hostel.view');

        $allocations = HostelAllocation::query()
            ->with(['room.hostel', 'student'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('allocated_date')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.hostel.allocations', ['allocations' => $allocations]);
    }

    public function vacate(HostelAllocation $allocation, HostelService $hostel)
    {
        $this->authorize('hostel.manage');

        $hostel->vacate($allocation);

        return redirect()->route('school-admin.hostel.allocations')->with('status', 'Allocation vacated.');
    }
}
