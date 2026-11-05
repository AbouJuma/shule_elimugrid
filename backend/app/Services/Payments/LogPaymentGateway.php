<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default driver (PAYMENT_GATEWAY_DRIVER unset or =log) — no real account
 * needed. There's no phone to push a prompt to, so it simulates the whole
 * checkout succeeding instantly rather than leaving the invoice "pending"
 * forever with nothing to ever resolve it.
 */
class LogPaymentGateway implements PaymentGateway
{
    public function checkout(string $externalId, string $msisdn, string $amount, string $currency, string $provider, array $extra = []): PaymentCheckoutResult
    {
        $fakeId = 'SIMULATED-'.Str::upper(Str::random(10));

        Log::info('Payment gateway checkout (simulated — no real provider configured)', [
            'external_id' => $externalId, 'msisdn' => $msisdn, 'amount' => $amount,
            'currency' => $currency, 'provider' => $provider, 'fake_transaction_id' => $fakeId,
        ]);

        return PaymentCheckoutResult::completed($fakeId, ['simulated' => true]);
    }
}
