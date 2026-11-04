<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TransportRoute */
class TransportRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->uuid,
                'registration_number' => $this->vehicle->registration_number,
            ] : null),
            'stops' => $this->whenLoaded('stops', fn () => $this->stops->map(fn ($stop) => [
                'id' => $stop->id,
                'name' => $stop->name,
                'sequence' => $stop->sequence,
                'latitude' => $stop->latitude !== null ? (float) $stop->latitude : null,
                'longitude' => $stop->longitude !== null ? (float) $stop->longitude : null,
                'pickup_time' => $stop->pickup_time,
                'dropoff_time' => $stop->dropoff_time,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
