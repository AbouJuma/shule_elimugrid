<?php

namespace App\Services\Payments;

class PaymentCheckoutResult
{
    private function __construct(
        public readonly string $status, // pending / completed / failed
        public readonly ?string $gatewayTransactionId,
        public readonly ?string $error,
        public readonly array $raw = [],
    ) {
    }

    /** The push was accepted by the gateway; the customer still has to approve it on their phone. */
    public static function pending(?string $gatewayTransactionId, array $raw = []): self
    {
        return new self('pending', $gatewayTransactionId, null, $raw);
    }

    /**
     * Used by LogPaymentGateway — nothing was actually sent to a real
     * gateway, so there's no phone prompt to wait for; the checkout is
     * treated as settled immediately so the feature works end to end
     * without a real account, same as LogSmsGateway for SMS.
     */
    public static function completed(?string $gatewayTransactionId, array $raw = []): self
    {
        return new self('completed', $gatewayTransactionId, null, $raw);
    }

    public static function failed(string $error, array $raw = []): self
    {
        return new self('failed', null, $error, $raw);
    }
}
