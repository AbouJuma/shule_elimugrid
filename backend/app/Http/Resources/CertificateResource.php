<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Certificate */
class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'title' => $this->title,
            'achievement' => $this->achievement,
            'issued_date' => $this->issued_date?->toDateString(),
            'status' => $this->status,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revocation_reason' => $this->revocation_reason,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->uuid,
                'name' => $this->student->fullName(),
                'admission_number' => $this->student->admission_number,
            ]),
            'template' => $this->whenLoaded('template', fn () => $this->template?->name),
            'issued_by' => $this->whenLoaded('issuedBy', fn () => $this->issuedBy?->name),
            'verification_url' => route('verify.certificate', $this->verification_token),
            'pdf_url' => route('certificates.pdf', $this->id),
        ];
    }
}
