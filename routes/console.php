<?php

use App\Models\Invoice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(function () {
    Invoice::query()
        ->whereIn('status', ['sent', 'partial'])
        ->whereDate('due_at', '<', today())
        ->update(['status' => 'overdue']);
})->dailyAt('08:00')->name('mark-overdue-invoices')->withoutOverlapping();
