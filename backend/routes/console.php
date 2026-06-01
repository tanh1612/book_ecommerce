<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:expire-vnpay')->everyFiveMinutes();
Schedule::command('promotions:sync-status')->everyMinute();
Schedule::command('orders:expire-manual-refunds')->daily();
Schedule::command('inventory:notify-low-stock')->daily();
Schedule::command('recommendations:build-popular')
    ->everySixHours()
    ->withoutOverlapping(30);
Schedule::command('recommendations:build-users')
    ->hourly()
    ->withoutOverlapping(30);
Schedule::command('recommendations:prune-interactions')
    ->daily()
    ->withoutOverlapping(30);
Schedule::command('ai:sync-book-rag-documents --pending')
    ->dailyAt('02:30')
    ->withoutOverlapping(60);
