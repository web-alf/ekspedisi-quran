<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Critical scheduled tasks for warehouse automation
Schedule::command('packing:daily-assignment')->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/packing-assignment.log'));

Schedule::command('packing:expire-tasks')->dailyAt('23:59')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/packing-expire.log'));

Schedule::command('packing:check-progress')->hourly()->between('08:00', '17:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/packing-progress.log'));

// Performance commands will be auto-discovered by Laravel

// Performance monitoring scheduled tasks
Schedule::command('performance:benchmark --output=json --iterations=3')
    ->weekly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/performance-benchmark.log'));

Schedule::command('performance:check-budgets --output=json')
    ->daily()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/performance-budgets.log'));

Schedule::command('cache:clear')->weekly();
Schedule::command('optimize:clear')->weekly();
