<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Drains the SMS/notification queue every minute so admissions, payment
 * confirmations and fee reminders go out automatically — no persistent
 * `queue:work` daemon required, which shared hosting (cPanel) can't run
 * anyway. The ONLY server setup this needs, once, is the standard Laravel
 * cron entry pointing at `schedule:run`; everything else (what runs, how
 * often) lives here and ships on every `git pull`.
 *
 *   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
