<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncInvoiceLinesDailyJob; // Import the daily job
use App\Jobs\SyncInvoiceLinesMonthlyJob; // Import the monthly job

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule the SyncInvoiceLinesDailyJob directly
Schedule::job(new SyncInvoiceLinesDailyJob)->dailyAt('23:00'); // Runs daily at 11 PM

// Schedule the SyncInvoiceLinesMonthlyJob directly
Schedule::job(new SyncInvoiceLinesMonthlyJob)->twiceMonthly(1, 16, '20:00'); // Runs on the 1st and 16th of the month at 8:00 PM
