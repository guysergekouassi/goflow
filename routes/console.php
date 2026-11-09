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

/*
|--------------------------------------------------------------------------
| Planification automatique du Rapport Hebdomadaire des Pointages
|--------------------------------------------------------------------------
| Exécution chaque vendredi à 18h00.
| Couvre la période du lundi 00h00 au vendredi 18h00.
| Évite les exécutions concurrentes et les doublons.
*/
Schedule::command('attendance:send-weekly-report')
    ->fridays()
    ->at('18:00')
    ->name('send-weekly-attendance-report')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| Planification automatique du Rapport Mensuel des Pointages
|--------------------------------------------------------------------------
| Exécution chaque minute : la commande vérifie pour chaque entreprise
| si la date et l'heure de génération paramétrées sont atteintes.
| Chaque entreprise dispose de sa propre configuration (jour + heure).
| Évite les exécutions concurrentes et les doublons.
*/
Schedule::command('attendance:send-monthly-report')
    ->everyMinute()
    ->name('send-monthly-attendance-report')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground();
