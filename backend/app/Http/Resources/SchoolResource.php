<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\School */
class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'registration_number' => $this->registration_number,
            'logo_url' => $this->logo_path ? Storage::url($this->logo_path) : null,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'status' => $this->status,
            'students_count' => $this->when(isset($this->students_count), fn () => $this->students_count),
            'subscription_package' => $this->whenLoaded('subscriptionPackage', fn () => [
                'id' => $this->subscriptionPackage->id,
                'name' => $this->subscriptionPackage->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
