<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Transport\StoreTransportRouteRequest;
use App\Http\Requests\Transport\UpdateTransportRouteRequest;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransportRouteController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('transport.view');

        $routes = TransportRoute::query()
            ->with(['vehicle', 'stops'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.transport-routes.index', [
            'routes' => $routes,
            'vehicles' => Vehicle::query()->orderBy('registration_number')->get(),
        ]);
    }

    public function store(StoreTransportRouteRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        $route = DB::transaction(function () use ($data, $tenant) {
            $route = TransportRoute::create([
                'school_id' => $tenant->id(),
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'name' => $data['name'],
                'status' => $data['status'] ?? 'active',
            ]);

            foreach ($data['stops'] ?? [] as $stop) {
                $route->stops()->create($stop);
            }

            return $route;
        });

        return redirect()->route('school-admin.transport-routes.index')
            ->with('status', __('Route ":p1" created.', ['p1' => $route->name]));
    }

    public function update(UpdateTransportRouteRequest $request, TransportRoute $transportRoute)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $transportRoute) {
            $transportRoute->update([
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'name' => $data['name'],
                'status' => $data['status'],
            ]);

            $transportRoute->stops()->delete();
            foreach ($data['stops'] ?? [] as $stop) {
                $transportRoute->stops()->create($stop);
            }
        });

        return redirect()->route('school-admin.transport-routes.index')
            ->with('status', __('Route ":p1" updated.', ['p1' => $transportRoute->name]));
    }

    public function destroy(TransportRoute $transportRoute)
    {
        $this->authorize('transport.manage');

        if ($transportRoute->students()->exists()) {
            return back()->withErrors(['route' => 'This route still has students assigned — reassign them first.']);
        }

        DB::transaction(function () use ($transportRoute) {
            $transportRoute->stops()->delete();
            $transportRoute->delete();
        });

        return redirect()->route('school-admin.transport-routes.index')
            ->with('status', __('Route ":p1" deleted.', ['p1' => $transportRoute->name]));
    }
}
