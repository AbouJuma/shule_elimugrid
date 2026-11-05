<?php

namespace App\Contracts;

use App\Services\Payments\PaymentCheckoutResult;

interface PaymentGateway
{
    /**
     * Push a mobile-money payment prompt to $msisdn for $amount, tagged
     * with our own $externalId so a later callback can be matched back to
     * the invoice that started it.
     */
    public function checkout(string $externalId, string $msisdn, string $amount, string $currency, string $provider): PaymentCheckoutResult;
}
