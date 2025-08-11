<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//Schedule::command(\App\Console\Commands\PollDividends::class)
//    ->everyFiveMinutes()
//    ->onOneServer()
//    ->withoutOverlapping()
//    ->runInBackground();

Schedule::call(fn() => app(\App\Services\DartCollector::class)->run())
    ->name('Dart Collector')
    ->everyFiveMinutes()->onOneServer()->withoutOverlapping();
