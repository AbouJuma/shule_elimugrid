<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateGatewayPaymentRequest extends FormRequest
{
    /** Ownership of the invoice (is this really the signed-in parent's own child?) is checked in the controller, not here. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'max:20'],
            // Mobile money (pushed to the phone's wallet) and bank (pushed
            // to the bank's own mobile-approval app) — same checkout flow
            // from the parent's point of view, just routed to a different
            // AzamPay endpoint internally (see AzamPayGateway::BANK_PROVIDERS).
            'provider' => ['required', Rule::in(['Airtel', 'Tigo', 'Halopesa', 'Azampesa', 'Mpesa', 'CRDB', 'NMB'])],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
