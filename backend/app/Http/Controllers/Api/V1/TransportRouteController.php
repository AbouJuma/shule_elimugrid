<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\StoreTransportRouteRequest;
use App\Http\Requests\Transport\UpdateTransportRouteRequest;
use App\Http\Resources\TransportRouteResource;
use App\Models\TransportRoute;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransportRouteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('transport.view');

        $routes = TransportRoute::query()
            ->with(['vehicle', 'stops'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return TransportRouteResource::collection($routes);
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

        return response()->json(['data' => new TransportRouteResource($route->load(['vehicle', 'stops']))], 201);
    }

    public function show(TransportRoute $transportRoute)
    {
        $this->authorize('transport.view');

        return new TransportRouteResource($transportRoute->load(['vehicle', 'stops', 'students']));
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

            // Update stops in place by id — recreating them would drop the students
            // assigned to each stop and the arrival history of past trips.
            $existing = $transportRoute->stops()->get()->keyBy('id');
            $keep = [];

            foreach ($data['stops'] ?? [] as $stop) {
                $id = $stop['id'] ?? null;
                unset($stop['id']);

                if ($id && $existing->has($id)) {
                    $existing[$id]->update($stop);
                    $keep[] = $id;
                } else {
                    $keep[] = $transportRoute->stops()->create($stop)->id;
                }
            }

            $transportRoute->stops()->whereNotIn('id', $keep)->delete();
        });

        return new TransportRouteResource($transportRoute->fresh(['vehicle', 'stops']));
    }

    public function destroy(TransportRoute $transportRoute)
    {
        $this->authorize('transport.manage');

        if ($transportRoute->students()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['route' => [__('This route still has students assigned — reassign them first.')]]);
        }

        if (\App\Models\TransportTrip::query()->where('transport_route_id', $transportRoute->id)->where('status', 'in_progress')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['route' => [__('This route has a trip in progress — end it first.')]]);
        }

        DB::transaction(function () use ($transportRoute) {
            $transportRoute->stops()->delete();
            $transportRoute->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }
}
