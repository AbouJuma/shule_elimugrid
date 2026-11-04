<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Vehicle */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'registration_number' => $this->registration_number,
            'make' => $this->make,
            'model' => $this->model,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->uuid,
                'name' => $this->driver->name,
                'phone' => $this->driver->phone,
            ] : null),
            'last_location' => $this->last_location_at ? [
                'latitude' => (float) $this->last_latitude,
                'longitude' => (float) $this->last_longitude,
                'recorded_at' => $this->last_location_at->toIso8601String(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
