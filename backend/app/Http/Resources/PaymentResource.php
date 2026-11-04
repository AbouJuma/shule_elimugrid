<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'receipt_number' => $this->receipt_number,
            'amount' => (string) $this->amount,
            'type' => $this->type,
            'method' => $this->method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_on' => $this->paid_on?->toDateString(),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
        ];
    }
}
