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
            'provider' => ['required', Rule::in(['Airtel', 'Tigo', 'Halopesa', 'Azampesa', 'Mpesa', 'CRDB', 'NMB'])],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            // Bank (CRDB/NMB) isn't a one-step push like mobile money: the
            // guardian must first dial their bank's own USSD code (outside
            // our app) to get an OTP, and give us the bank account number
            // separately from their phone number — AzamPay's bank/checkout
            // requires both.
            'otp' => ['required_if:provider,CRDB,NMB', 'nullable', 'string', 'max:20'],
            'account_number' => ['required_if:provider,CRDB,NMB', 'nullable', 'string', 'max:50'],
        ];
    }
}
