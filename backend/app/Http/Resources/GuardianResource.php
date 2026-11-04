<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Guardian */
class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'occupation' => $this->occupation,
            'address' => $this->address,
            'has_login' => $this->user_id !== null,
            'locale' => $this->locale,
            'relationship' => $this->whenPivotLoaded('student_guardian', fn () => $this->pivot->relationship),
            'is_primary' => $this->whenPivotLoaded('student_guardian', fn () => (bool) $this->pivot->is_primary),
        ];
    }
}
