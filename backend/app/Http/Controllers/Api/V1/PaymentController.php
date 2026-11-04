<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\RecordPaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\FeeMessageService;
use App\Services\PaymentService;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(RecordPaymentRequest $request, Invoice $invoice, PaymentService $payments, FeeMessageService $messages)
    {
        $payment = $payments->record($invoice, $request->validated(), $request->user());

        try {
            $messages->forPayment($payment, $request->user());
        } catch (ValidationException) {
            // No guardian phone on file — the payment itself already succeeded.
        }

        return new InvoiceResource($invoice->fresh(['student', 'bankAccount', 'items', 'payments']));
    }
}
