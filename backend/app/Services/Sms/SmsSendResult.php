<?php

namespace App\Services\Sms;

class SmsSendResult
{
    private function __construct(
        public readonly string $status, // sent / simulated / failed
        public readonly ?string $providerMessageId,
        public readonly ?string $error,
    ) {
    }

    public static function sent(?string $providerMessageId): self
    {
        return new self('sent', $providerMessageId, null);
    }

    /**
     * Used by LogSmsGateway — nothing was actually transmitted. Kept
     * distinct from `sent` so the portal never implies a message reached
     * a real phone when SMS_DRIVER=log.
     */
    public static function simulated(?string $providerMessageId): self
    {
        return new self('simulated', $providerMessageId, null);
    }

    public static function failure(string $error): self
    {
        return new self('failed', null, $error);
    }
}
