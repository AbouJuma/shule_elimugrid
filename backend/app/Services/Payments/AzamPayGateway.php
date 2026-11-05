<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AzamPay (https://azampay.co.tz) checkout — mobile money AND bank, built
 * against their documented sandbox API (token auth against the
 * Authenticator service, then a push request to the Checkout service's
 * /mno/checkout or /bank/checkout). Not verified against a live sandbox
 * account: no real AzamPay credentials exist in this dev environment yet.
 * Treat the request/response shape here as best-effort-accurate to their
 * public docs, not confirmed working — worth a live smoke test with real
 * sandbox credentials before relying on it (same caveat BeemSmsGateway
 * shipped with before it was verified). The /bank/checkout shape is the
 * less-documented of the two publicly — double-check it first.
 */
class AzamPayGateway implements PaymentGateway
{
    /** Providers AzamPay routes through /azampay/bank/checkout rather than /azampay/mno/checkout. */
    public const BANK_PROVIDERS = ['CRDB', 'NMB'];

    public function __construct(
        private readonly string $authBaseUrl,
        private readonly string $checkoutBaseUrl,
        private readonly string $appName,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function checkout(string $externalId, string $msisdn, string $amount, string $currency, string $provider): PaymentCheckoutResult
    {
        $token = $this->token();

        if (! $token) {
            return PaymentCheckoutResult::failed('Could not authenticate with the payment provider.');
        }

        $endpoint = in_array($provider, self::BANK_PROVIDERS, true) ? 'bank' : 'mno';

        $payload = [
            'accountNumber' => $msisdn,
            'amount' => $amount,
            'currency' => $currency,
            'externalId' => $externalId,
            'provider' => $provider,
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$this->checkoutBaseUrl}/azampay/{$endpoint}/checkout", $payload);
        } catch (\Throwable $e) {
            Log::warning('AzamPay checkout failed (network error)', ['external_id' => $externalId, 'channel' => $endpoint, 'error' => $e->getMessage()]);

            return PaymentCheckoutResult::failed('Could not reach the payment provider: '.$e->getMessage());
        }

        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['success'] ?? true) === false) {
            Log::warning('AzamPay checkout rejected', ['external_id' => $externalId, 'channel' => $endpoint, 'status' => $response->status(), 'body' => $body]);

            return PaymentCheckoutResult::failed($body['message'] ?? "Payment provider returned HTTP {$response->status()}.", $body);
        }

        // Field name is a best guess ('transactionId' per AzamPay's docs) —
        // confirm against a real sandbox response once credentials exist.
        return PaymentCheckoutResult::pending($body['transactionId'] ?? $body['data']['transactionId'] ?? null, $body);
    }

    /**
     * Cached ~55 minutes — AzamPay sandbox tokens are documented as
     * 1-hour-lived. A failed fetch is never cached, so the next checkout
     * attempt always gets a fresh try instead of being stuck for the rest
     * of the TTL.
     */
    protected function token(): ?string
    {
        if ($cached = Cache::get('azampay_access_token')) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->post("{$this->authBaseUrl}/AppRegistrationService/GenerateToken", [
                'appName' => $this->appName,
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AzamPay token request failed (network error)', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('AzamPay token request rejected', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $body = $response->json() ?? [];
        $token = $body['data']['accessToken'] ?? $body['accessToken'] ?? null;

        if ($token) {
            Cache::put('azampay_access_token', $token, 3300);
        }

        return $token;
    }
}
