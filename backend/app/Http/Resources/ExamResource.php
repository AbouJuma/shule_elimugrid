<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Exam */
class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'classes' => $this->whenLoaded('classes', fn () => $this->classes->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])),
            'academic_year' => $this->whenLoaded('academicYear', fn () => $this->academicYear->name),
            'subjects' => $this->whenLoaded('subjects', fn () => $this->subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'max_marks' => $subject->pivot->max_marks,
            ])),
        ];
    }
}
