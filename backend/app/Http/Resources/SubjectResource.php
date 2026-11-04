<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subject */
class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'classes' => $this->whenLoaded('classes', fn () => $this->classes->pluck('name')),
            'class_ids' => $this->whenLoaded('classes', fn () => $this->classes->pluck('id')->values()),
        ];
    }
}
