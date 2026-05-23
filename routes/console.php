<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('images:clean-expired --hours=2')->hourly()->withoutOverlapping();
Schedule::command('images:clean-expired --temp')->hourly()->withoutOverlapping();
Schedule::command('generation:recover-stuck')->everyMinute()->withoutOverlapping();
