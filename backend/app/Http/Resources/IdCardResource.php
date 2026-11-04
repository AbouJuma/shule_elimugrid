<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\IdCard */
class IdCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'card_number' => $this->card_number,
            'status' => $this->status,
            'issued_date' => $this->issued_date?->toDateString(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revocation_reason' => $this->revocation_reason,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->uuid,
                'name' => $this->student->fullName(),
                'admission_number' => $this->student->admission_number,
            ]),
            'issued_by' => $this->whenLoaded('issuedBy', fn () => $this->issuedBy?->name),
            'verification_url' => route('verify.id-card', $this->verification_token),
            'pdf_url' => route('id-cards.pdf', $this->id),
        ];
    }
}
