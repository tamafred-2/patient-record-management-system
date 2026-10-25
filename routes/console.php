<?php

use App\Support\FollowUpReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('followups:remind', function () {
    $this->info(FollowUpReminders::dispatchDue(true).' follow-up reminder(s) sent.');
})->purpose('Notify staff about follow-up return visits that are due');

// Optional: open pages already trigger this check; the scheduler only helps when nobody is signed in.
Schedule::command('followups:remind')->hourly();

Schedule::command('database:backup')->dailyAt('18:00')->timezone('Asia/Manila')->withoutOverlapping();
