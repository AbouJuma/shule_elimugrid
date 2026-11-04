<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Spatie\Permission\Models\Role */
class RoleResource extends JsonResource
{
    /**
     * Passed in explicitly rather than read via withCount('users')/
     * loadCount('users') — see App\Support\Tenancy\RoleUserCount for why
     * that relation-based count crashes under Sanctum auth.
     */
    public ?int $usersCount = null;

    public function withUsersCount(?int $count): static
    {
        $this->usersCount = $count;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
            'users_count' => $this->usersCount,
            'is_default' => in_array($this->name, config('roles.default_school_roles'), true),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
