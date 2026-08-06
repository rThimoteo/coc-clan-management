<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (! config('services.clash_of_clans.demo_mode')) {
    Schedule::command('wars:refresh-active')
        ->everyFiveMinutes()
        ->withoutOverlapping(10);

    Schedule::command('members:sync')
        ->dailyAt('03:00')
        ->timezone(config('app.schedule_timezone'))
        ->withoutOverlapping(30);

    Schedule::command('wars:sync')
        ->dailyAt('03:15')
        ->timezone(config('app.schedule_timezone'))
        ->withoutOverlapping(30);

    Schedule::command('cwl:sync')
        ->dailyAt('03:30')
        ->timezone(config('app.schedule_timezone'))
        ->when(fn (): bool => now(config('app.schedule_timezone'))->day > 10)
        ->withoutOverlapping(30);

    Schedule::command('cwl:sync')
        ->everyTwoHours()
        ->between('01:00', '23:00')
        ->timezone(config('app.schedule_timezone'))
        ->when(fn (): bool => now(config('app.schedule_timezone'))->day <= 10)
        ->withoutOverlapping(30);
}
