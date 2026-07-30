<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (! config('services.clash_of_clans.demo_mode')) {
    Schedule::command('members:sync')
        ->dailyAt('03:00')
        ->timezone(config('app.schedule_timezone'))
        ->withoutOverlapping(30);

    Schedule::command('wars:sync')
        ->dailyAt('03:15')
        ->timezone(config('app.schedule_timezone'))
        ->withoutOverlapping(30);
}
