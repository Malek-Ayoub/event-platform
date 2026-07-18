<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tickets/email only leave the outbox when a worker drains it; poll every minute.
Schedule::command('outbox:process --once')->everyMinute()->withoutOverlapping()->onOneServer();

// Abandoned checkouts hold reserved inventory until this reclaim job runs.
Schedule::command('orders:expire-stale')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
