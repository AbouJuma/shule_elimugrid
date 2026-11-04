<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'invoice_number' => $this->invoice_number,
            'control_number' => $this->control_number,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->uuid,
                'name' => $this->student->fullName(),
                'admission_number' => $this->student->admission_number,
            ]),
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount ? [
                'bank_name' => $this->bankAccount->bank_name,
                'account_name' => $this->bankAccount->account_name,
                'account_number' => $this->bankAccount->account_number,
            ] : null),
            'total_amount' => (string) $this->total_amount,
            'amount_paid' => (string) $this->amount_paid,
            'balance' => $this->balance(),
            'status' => $this->status,
            'issued_date' => $this->issued_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'amount' => (string) $item->amount,
            ])),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
