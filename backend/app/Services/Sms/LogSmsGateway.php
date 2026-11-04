<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default gateway — writes to the application log instead of making a
 * real network call. Lets the whole communications feature (templates,
 * logging, portal UI, queued sending) work and be manually tested end to
 * end without a real Beem account. Switch to 'beem' via SMS_DRIVER once
 * real credentials exist; nothing else about the feature changes.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsSendResult
    {
        $id = (string) Str::uuid();

        Log::info('[SMS:log-driver] Message not actually sent — SMS_DRIVER=log', [
            'to' => $to,
            'message' => $message,
            'simulated_provider_message_id' => $id,
        ]);

        return SmsSendResult::simulated($id);
    }
}
