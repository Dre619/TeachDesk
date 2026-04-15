<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send subscription expiry warnings every day at 8am
Schedule::command('subscriptions:send-expiry-warnings')->dailyAt('08:00');
