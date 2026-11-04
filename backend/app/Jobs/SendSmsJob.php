<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\SmsLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued so composing a message to many recipients (e.g. every guardian
 * in a class) doesn't hold the request open for N gateway round-trips —
 * matches this app's existing "database queue, cron-triggered
 * queue:work" architecture (see FILE_PLAN's deviations section).
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public SmsLog $smsLog)
    {
    }

    public function handle(SmsGateway $gateway): void
    {
        $result = $gateway->send($this->smsLog->recipient_phone, $this->smsLog->message);

        $this->smsLog->update([
            'status' => $result->status,
            'provider_message_id' => $result->providerMessageId,
            'error' => $result->error,
            'sent_at' => $result->status === 'failed' ? null : now(),
        ]);
    }
}
