<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SMS/notifications are sent inline from the request now (see
// SmsService::dispatch) rather than queued, so there's nothing left to
// drain on a schedule — no cron setup needed on the server for this.
