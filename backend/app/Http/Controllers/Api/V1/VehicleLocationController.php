<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\UpdateVehicleLocationRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;

class VehicleLocationController extends Controller
{
    public function store(UpdateVehicleLocationRequest $request, Vehicle $vehicle)
    {
        $data = $request->validated();

        $vehicle->recordLocation((float) $data['latitude'], (float) $data['longitude']);

        return new VehicleResource($vehicle->fresh());
    }
}
