<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vérifie chaque jour à 07h00 les plannings arrivés à échéance
// et crée les notifications de rappel correspondantes.
Schedule::command('planning:check-echeances')->dailyAt('07:00');
