<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'locale' => $this->locale,
            'school' => $this->when($this->school_id, fn () => [
                'id' => $this->school?->uuid,
                'name' => $this->school?->name,
                'status' => $this->school?->status,
            ]),
            'campus_id' => $this->campus?->id,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $this->relationLoaded('roles') || $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name'),
            ),
            'is_platform_user' => $this->isPlatformUser(),
        ];
    }
}
