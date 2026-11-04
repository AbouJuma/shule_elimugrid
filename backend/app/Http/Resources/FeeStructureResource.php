<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FeeStructure */
class FeeStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'school_class' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass->name),
            'academic_year' => $this->whenLoaded('academicYear', fn () => $this->academicYear->name),
            'term' => $this->whenLoaded('term', fn () => $this->term?->name),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => $item->amount,
            ])),
            'total' => $this->whenLoaded('items', fn () => (string) $this->items->sum('amount')),
        ];
    }
}
