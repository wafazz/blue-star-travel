<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Apply period-based tier re-qualification. Self-guards: only acts when a Jan–Jun / Jul–Dec period rolls over.
Schedule::command('tiers:requalify')->dailyAt('00:30');
