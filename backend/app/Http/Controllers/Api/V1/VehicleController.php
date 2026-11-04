<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\StoreVehicleRequest;
use App\Http\Requests\Transport\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\TransportTrip;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('transport.view');

        $vehicles = Vehicle::query()
            ->with('driver')
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'registration_number', 'like', '%'.$request->string('search').'%'
            ))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return VehicleResource::collection($vehicles);
    }

    public function store(StoreVehicleRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        $vehicle = Vehicle::create([
            ...$data,
            'school_id' => $tenant->id(),
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json(['data' => new VehicleResource($vehicle->load('driver'))], 201);
    }

    public function show(Vehicle $vehicle)
    {
        $this->authorize('transport.view');

        return new VehicleResource($vehicle->load('driver'));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        $vehicle->update($request->validated());

        return new VehicleResource($vehicle->fresh('driver'));
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('transport.manage');

        if (TransportTrip::query()->where('vehicle_id', $vehicle->id)->where('status', 'in_progress')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['vehicle' => [__('This vehicle has a trip in progress — end it first.')]]);
        }

        $vehicle->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
