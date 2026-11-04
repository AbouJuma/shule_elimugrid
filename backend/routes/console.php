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
 *
 * Schedule::command(...) would normally shell out to a fresh `php artisan`
 * process via Symfony's Process component — which needs proc_open, and
 * some shared hosts (this one included) disable it entirely. Running the
 * queue worker via Artisan::call() instead executes it in-process, in the
 * same PHP request `schedule:run` is already in, so it never needs
 * proc_open/exec at all.
 */
Schedule::call(function () {
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--max-time' => 50,
        '--tries' => 3,
    ]);
})->name('drain-notification-queue')->everyMinute()->withoutOverlapping();
