<?php

namespace App\Contracts;

use App\Services\Payments\PaymentCheckoutResult;

interface PaymentGateway
{
    /**
     * Push a mobile-money or bank payment prompt to $msisdn for $amount,
     * tagged with our own $externalId so a later callback can be matched
     * back to the invoice that started it.
     *
     * $extra carries channel-specific fields a bank checkout needs that a
     * mobile-money one doesn't — AzamPay's bank checkout requires an `otp`
     * (the customer dials a USSD code on their own phone to get this from
     * their bank — we can't generate it) and a separate bank
     * `account_number` distinct from the phone number in $msisdn.
     */
    public function checkout(string $externalId, string $msisdn, string $amount, string $currency, string $provider, array $extra = []): PaymentCheckoutResult;
}
