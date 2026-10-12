<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retrain the recommender every night so new orders and views are picked up.
Schedule::command('recommender:sync')->dailyAt('02:00')->withoutOverlapping();
