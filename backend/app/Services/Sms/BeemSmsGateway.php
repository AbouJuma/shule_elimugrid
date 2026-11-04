<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Beem Africa bulk SMS gateway (https://beem.africa) — built against
 * their documented v1 send API (Basic-auth over the API key/secret pair,
 * JSON body with a `recipients` array). Not verified against a live Beem
 * account: no real credentials exist in this dev environment. Treat the
 * request/response shape here as best-effort-accurate to their public
 * docs, not confirmed working — worth a live smoke test with real
 * credentials before relying on it in production.
 */
class BeemSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $senderId,
    ) {
    }

    public function send(string $to, string $message): SmsSendResult
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
                ->timeout(10)
                ->post("{$this->baseUrl}/send", [
                    'source_addr' => $this->senderId,
                    'encoding' => 0,
                    'message' => $message,
                    'recipients' => [
                        ['recipient_id' => 1, 'dest_addr' => $to],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Beem SMS send failed (network error)', ['to' => $to, 'error' => $e->getMessage()]);

            return SmsSendResult::failure('Could not reach the SMS provider: '.$e->getMessage());
        }

        if (! $response->successful()) {
            Log::warning('Beem SMS send failed (HTTP error)', [
                'to' => $to, 'status' => $response->status(), 'body' => $response->body(),
            ]);

            return SmsSendResult::failure("SMS provider returned HTTP {$response->status()}.");
        }

        $body = $response->json();

        if (($body['successful'] ?? false) !== true) {
            $error = $body['message'] ?? 'SMS provider rejected the message.';
            Log::warning('Beem SMS send rejected', ['to' => $to, 'body' => $body]);

            return SmsSendResult::failure($error);
        }

        return SmsSendResult::sent(isset($body['request_id']) ? (string) $body['request_id'] : null);
    }
}
