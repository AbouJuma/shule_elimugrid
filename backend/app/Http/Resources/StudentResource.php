<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Student */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'admission_number' => $this->admission_number,
            'control_number' => $this->control_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'admission_date' => $this->admission_date?->toDateString(),
            'status' => $this->status,
            'has_login' => $this->user_id !== null,
            'photo_url' => $this->photo_path ? Storage::url($this->photo_path) : null,
            'school_class' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass ? [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
            ] : null),
            'stream' => $this->whenLoaded('stream', fn () => $this->stream ? [
                'id' => $this->stream->id,
                'name' => $this->stream->name,
            ] : null),
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),
            'transport_route' => $this->whenLoaded('transportRoute', fn () => $this->transportRoute ? [
                'id' => $this->transportRoute->uuid,
                'name' => $this->transportRoute->name,
            ] : null),
            'route_stop' => $this->whenLoaded('routeStop', fn () => $this->routeStop ? [
                'id' => $this->routeStop->id,
                'name' => $this->routeStop->name,
            ] : null),
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
