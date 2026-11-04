<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Transport\StoreVehicleRequest;
use App\Http\Requests\Transport\UpdateVehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class VehicleController extends \App\Http\Controllers\Controller
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
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.vehicles.index', [
            'vehicles' => $vehicles,
            'drivers' => User::query()->role('Driver')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreVehicleRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        $vehicle = Vehicle::create([
            ...$data,
            'school_id' => $tenant->id(),
            'status' => $data['status'] ?? 'active',
        ]);

        return redirect()->route('school-admin.vehicles.index')
            ->with('status', __('Vehicle ":p1" added.', ['p1' => $vehicle->registration_number]));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        $vehicle->update($request->validated());

        return redirect()->route('school-admin.vehicles.index')
            ->with('status', __('Vehicle ":p1" updated.', ['p1' => $vehicle->registration_number]));
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('transport.manage');

        $vehicle->delete();

        return redirect()->route('school-admin.vehicles.index')
            ->with('status', __('Vehicle ":p1" deleted.', ['p1' => $vehicle->registration_number]));
    }
}
