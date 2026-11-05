<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AzamPay (https://azampay.co.tz) checkout — mobile money AND bank, built
 * against https://developerdocs.azampay.co.tz/tanzania/token-generation
 * and /tanzania/checkout-api, and live-smoke-tested against a real
 * sandbox account (see AZAMPAY_CLIENT_ID in .env.example):
 *
 * - Token endpoint is /AppRegistration/GenerateToken (NOT
 *   /AppRegistrationService/GenerateToken, which silently 401s with an
 *   identical "UnAuthorized Access" for every request shape — that wrong
 *   path cost real debugging time before the docs caught it).
 * - Checkout calls take the token as a plain `Authorization: Bearer`
 *   header — confirmed live, mno/checkout returned a real transactionId.
 * - Bank checkout is NOT a drop-in replacement for mobile money: it needs
 *   an `otp` the customer gets by dialing a USSD code on their own phone
 *   (e.g. *150*03# for CRDB, *150*66# for NMB) *outside* our app, plus a
 *   bank account number distinct from their phone number. There's no
 *   confirmed way to request that OTP from our side — AzamPay's own docs
 *   don't document the /azampay/bank/otp request/response shape at all,
 *   so this class only accepts an OTP the customer already has; it does
 *   not request one.
 * - Bank checkout also needs `merchantName` — live-tested: omitting it (as
 *   the docs' "optional" implied) gets rejected with "Invalid Length of
 *   field: Merchant Name", so it's required in practice.
 * - Bank's response shape is NOT the same as mobile money's: a bank error
 *   message comes back as `msg`, not `message`; a bank success nests its
 *   reference at `data.properties.ReferenceID`, not a flat `transactionId`.
 *   Both confirmed live against the real sandbox.
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

    public function checkout(string $externalId, string $msisdn, string $amount, string $currency, string $provider, array $extra = []): PaymentCheckoutResult
    {
        $token = $this->token();

        if (! $token) {
            return PaymentCheckoutResult::failed('Could not authenticate with the payment provider.');
        }

        $isBank = in_array($provider, self::BANK_PROVIDERS, true);
        $endpoint = $isBank ? 'bank' : 'mno';

        if ($isBank) {
            if (empty($extra['otp'])) {
                return PaymentCheckoutResult::failed('A bank OTP is required — the guardian must dial their bank\'s USSD code to get one.');
            }

            $payload = [
                'amount' => $amount,
                'currencyCode' => $currency,
                'merchantAccountNumber' => $extra['account_number'] ?? $msisdn,
                'merchantMobileNumber' => $msisdn,
                'merchantName' => $extra['account_name'] ?? 'ElimuGrid Guardian',
                'otp' => $extra['otp'],
                'provider' => $provider,
                'referenceId' => $externalId,
            ];
        } else {
            $payload = [
                'accountNumber' => $msisdn,
                'amount' => $amount,
                'currency' => $currency,
                'externalId' => $externalId,
                'provider' => $provider,
            ];
        }

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

            $message = $body['message'] ?? $body['msg'] ?? null;

            return PaymentCheckoutResult::failed($message ?: "Payment provider returned HTTP {$response->status()}.", $body);
        }

        $transactionId = $isBank
            ? ($body['data']['properties']['ReferenceID'] ?? null)
            : ($body['transactionId'] ?? null);

        return PaymentCheckoutResult::pending($transactionId, $body);
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
            $response = Http::timeout(10)->post("{$this->authBaseUrl}/AppRegistration/GenerateToken", [
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
        $token = $body['data']['accessToken'] ?? null;

        if ($token) {
            Cache::put('azampay_access_token', $token, 3300);
        }

        return $token;
    }
}
