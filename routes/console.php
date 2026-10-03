<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// cPanel: jalankan `php artisan schedule:run` via Cron tiap 5 menit.
// Tanpa queue worker / daemon (aturan shared hosting).
Schedule::command('shopee:refresh-tokens')->everyFiveMinutes();
Schedule::command('shopee:process-webhooks')->everyFiveMinutes();
