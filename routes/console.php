<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Console Task Scheduling
|--------------------------------------------------------------------------
|
| Define scheduled commands for LiveBids background tasks.
|
*/

// Check and mark missed auctions every minute (10m grace period)
Schedule::command('auctions:mark-missed')->everyMinute();

// Dispatch upcoming auction reminder notifications every minute
Schedule::command('auctions:send-reminders')->everyMinute();

// Auto-release escrow for inspection periods that have lapsed without disputes
Schedule::command('orders:auto-release-escrow')->everyFiveMinutes();

// Reconcile and audit double-entry ledger balance parity every midnight
Schedule::command('ledger:reconcile')->dailyAt('00:00');
