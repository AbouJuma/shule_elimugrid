<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hostel\AllocateRoomRequest;
use App\Http\Requests\Hostel\StoreHostelRequest;
use App\Http\Requests\Hostel\StoreHostelRoomRequest;
use App\Http\Requests\Hostel\UpdateHostelRequest;
use App\Http\Requests\Hostel\UpdateHostelRoomRequest;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Services\HostelService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

/** Hostels, rooms and allocations for the mobile app. */
class HostelController extends Controller
{
    public function index()
    {
        $this->authorize('hostel.view');

        return response()->json(['data' => Hostel::query()->with(['warden', 'rooms'])->orderBy('name')->get()->map(fn ($h) => $this->hostel($h))->values()]);
    }

    public function store(StoreHostelRequest $request, TenantContext $tenant)
    {
        $hostel = Hostel::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return response()->json(['data' => $this->hostel($hostel->load(['warden', 'rooms']))], 201);
    }

    public function update(UpdateHostelRequest $request, int $hostel)
    {
        $model = Hostel::query()->findOrFail($hostel);
        $model->update($request->validated());

        return response()->json(['data' => $this->hostel($model->fresh(['warden', 'rooms']))]);
    }

    public function destroy(int $hostel)
    {
        $this->authorize('hostel.manage');
        $model = Hostel::query()->findOrFail($hostel);

        if ($model->rooms()->exists()) {
            return $this->refuse('hostel', __('This hostel still has rooms — delete them first.'));
        }

        $model->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function rooms(Request $request)
    {
        $this->authorize('hostel.view');

        return response()->json(['data' => HostelRoom::query()->with('hostel')
            ->when($request->integer('hostel_id'), fn ($q, $id) => $q->where('hostel_id', $id))
            ->orderBy('room_number')->get()->map(fn ($r) => $this->room($r))->values()]);
    }

    public function storeRoom(StoreHostelRoomRequest $request, TenantContext $tenant)
    {
        $room = HostelRoom::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return response()->json(['data' => $this->room($room->load('hostel'))], 201);
    }

    public function updateRoom(UpdateHostelRoomRequest $request, int $room)
    {
        $model = HostelRoom::query()->findOrFail($room);
        $model->update($request->validated());

        return response()->json(['data' => $this->room($model->fresh('hostel'))]);
    }

    public function destroyRoom(int $room)
    {
        $this->authorize('hostel.manage');
        $model = HostelRoom::query()->findOrFail($room);

        if ($model->occupied_count > 0) {
            return $this->refuse('room', __('This room still has students allocated — vacate them first.'));
        }

        $model->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function allocate(AllocateRoomRequest $request, int $room, HostelService $hostel)
    {
        $model = HostelRoom::query()->findOrFail($room);
        $student = Student::query()->findOrFail($request->validated()['student_id']);

        $allocation = $hostel->allocate($model, $student, $request->user());

        return response()->json(['data' => $this->allocation($allocation->load(['room.hostel', 'student']))], 201);
    }

    public function allocations(Request $request)
    {
        $this->authorize('hostel.view');

        return response()->json(['data' => HostelAllocation::query()->with(['room.hostel', 'student'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('allocated_date')->latest('id')->limit(200)->get()->map(fn ($a) => $this->allocation($a))->values()]);
    }

    public function vacate(int $allocation, HostelService $hostel)
    {
        $this->authorize('hostel.manage');
        $model = HostelAllocation::query()->findOrFail($allocation);
        $hostel->vacate($model);

        return response()->json(['data' => $this->allocation($model->fresh(['room.hostel', 'student']))]);
    }

    protected function refuse(string $field, string $message)
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }

    protected function hostel(Hostel $h): array
    {
        return [
            'id' => $h->id, 'name' => $h->name, 'gender' => $h->gender,
            'warden' => $h->warden ? ['id' => $h->warden->uuid, 'name' => $h->warden->name] : null,
            'rooms' => $h->rooms->count(),
            'capacity' => (int) $h->rooms->sum('capacity'),
            'occupied' => (int) $h->rooms->sum('occupied_count'),
        ];
    }

    protected function room(HostelRoom $r): array
    {
        return [
            'id' => $r->id, 'hostel_id' => $r->hostel_id, 'hostel' => $r->hostel?->name, 'room_number' => $r->room_number,
            'capacity' => $r->capacity, 'occupied_count' => $r->occupied_count,
        ];
    }

    protected function allocation(HostelAllocation $a): array
    {
        return [
            'id' => $a->id,
            'student' => $a->student?->fullName(),
            'room' => $a->room ? $a->room->hostel?->name.' · '.$a->room->room_number : null,
            'allocated_date' => $a->allocated_date?->toDateString(),
            'vacated_date' => $a->vacated_date?->toDateString(),
            'status' => $a->status,
        ];
    }
}
