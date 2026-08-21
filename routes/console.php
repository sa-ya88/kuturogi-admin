<?php

use App\Support\DemoMode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$hours = DemoMode::refreshHours();

Schedule::command('demo:refresh')
    ->cron(sprintf('0 */%d * * *', $hours))
    ->withoutOverlapping();
