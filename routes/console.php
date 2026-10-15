<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rapport de présence de la semaine, envoyé à l'admin de chaque entreprise.
// Nécessite sur le serveur le cron : * * * * * php artisan schedule:run
Schedule::command('rapports:absences-hebdo')
    ->fridays()
    ->at('17:00')
    ->withoutOverlapping();
