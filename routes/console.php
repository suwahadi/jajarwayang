<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jaring pengaman pembayaran bila webhook Midtrans gagal terkirim.
Schedule::command('payments:midtrans:reconcile')->everyFifteenMinutes();
