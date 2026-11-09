<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyAttendanceReportJob;
use App\Models\Company;
use App\Models\MonthlyReportConfig;
use App\Services\MonthlyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMonthlyAttendanceReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:send-monthly-report
                            {--company= : ID ou nom de l\'entreprise spécifique}
                            {--month= : Mois cible du rapport (format YYYY-MM, ex: 2026-10)}
                            {--to= : Adresse e-mail personnalisée pour un envoi de test direct}
                            {--force : Forcer la génération et l\'envoi même si déjà envoyé ce mois}
                            {--sync : Exécuter l\'envoi en mode synchrone (sans passer par la file d\'attente)}
                            {--dry-run : Générer et afficher les données sans envoyer d\'e-mail ni enregistrer d\'historique}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère et expédie le rapport mensuel des pointages aux responsables RH';

    /**
     * Execute the console command.
     */
    public function handle(MonthlyAttendanceReportService $service): int
    {
        $companyFilter = $this->option('company');
        $customMonth = $this->option('month');
        $customTo = $this->option('to');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync') || !empty($customTo);
        $dryRun = (bool) $this->option('dry-run');

        $this->info("===================================================================");
        $this->info("    GP FLOW — Génération & Envoi du Rapport Mensuel RH             ");
        $this->info("===================================================================");

        $referenceMonth = null;
        $isTargetMonth = false;
        if ($customMonth) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $customMonth)) {
                $this->error("Format de mois invalide: {$customMonth}. Utilisez le format YYYY-MM (ex: 2026-10).");
                return Command::FAILURE;
            }

            try {
                $referenceMonth = Carbon::createFromFormat('!Y-m', $customMonth)->startOfMonth();
                $isTargetMonth = true;
            } catch (\Throwable $e) {
                $this->error("Format de mois invalide: {$customMonth}. Utilisez le format YYYY-MM (ex: 2026-10).");
                return Command::FAILURE;
            }
        }

        // Sélection des entreprises
        $query = Company::query();

        if ($companyFilter) {
            if (is_numeric($companyFilter)) {
                $query->where('id', $companyFilter);
            } else {
                $query->where('name', 'like', "%{$companyFilter}%");
            }
        } else {
            // Filtrer les entreprises actives avec rapport mensuel activé
            $query->where('is_active', true)
                ->whereHas('monthlyReportConfig', function ($q) {
                    $q->where('is_enabled', true);
                });

            if ($customTo) {
                $query->limit(1);
            }
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            $this->warn("Aucune entreprise active trouvée avec rapport mensuel activé.");
            return Command::SUCCESS;
        }

        $this->info("Entreprises éligibles trouvées : " . $companies->count());

        $stats = [
            'total'   => $companies->count(),
            'sent'    => 0,
            'queued'  => 0,
            'skipped' => 0,
            'failed'  => 0,
        ];

        foreach ($companies as $company) {
            $this->line("");
            $this->line("<comment>► Analyse de : {$company->name} (ID: {$company->id})</comment>");

            $config = $service->getOrCreateConfig($company);

            // Vérification de la planification horaire si exécuté en tâche de fond automatique
            // (pas de filtre entreprise, pas de mois forcé, pas de paramètre force)
            if (!$companyFilter && !$customMonth && !$force && !$dryRun) {
                if (!$config->auto_send_enabled || !$config->is_enabled) {
                    $this->line("  [Ignoré] Envoi automatique désactivé pour cette entreprise.");
                    $stats['skipped']++;
                    continue;
                }

                $tz = $service->resolveTimezone($company);
                $now = Carbon::now($tz);

                // Si le jour configuré n'existe pas dans le mois (ex: 30 en février), utiliser le dernier jour du mois
                $targetSendDay = min((int) ($config->send_day ?: 2), (int) $now->daysInMonth);
                $currentDay = (int) $now->day;
                $currentHour = (int) $now->hour;
                $currentMinute = (int) $now->minute;
                $configuredHour = (int) ($config->send_hour ?? 8);
                $configuredMinute = (int) ($config->send_minute ?? 0);

                if ($currentDay !== $targetSendDay || $currentHour !== $configuredHour || $currentMinute !== $configuredMinute) {
                    $this->line("  [En attente] Créneau non atteint (Config: Jour {$targetSendDay} à " . sprintf('%02d:%02d', $configuredHour, $configuredMinute) . " | Actuel: Jour {$currentDay} à " . sprintf('%02d:%02d', $currentHour, $currentMinute) . " {$tz}).");
                    $stats['skipped']++;
                    continue;
                }
            }

            try {
                $customRecipients = $customTo ? array_values(array_filter(array_map('trim', explode(',', $customTo)))) : null;

                if ($dryRun) {
                    $this->info("  [Mode Simulation — Dry Run]");
                    $reportData = $service->buildReportData($company, $referenceMonth, $isTargetMonth);
                    $period = $reportData['period'];
                    $ind = $reportData['indicators'];
                    $recipients = $customRecipients ?? $reportData['recipients'];

                    $this->table(
                        ['Paramètre', 'Valeur'],
                        [
                            ['Entreprise', $company->name . ' (ID: ' . $company->id . ')'],
                            ['Mois du rapport', $period['report_month'] . ' (' . $period['month_name_fr'] . ' ' . $period['year'] . ')'],
                            ['Période couverte', $period['start_date_fr'] . ' au ' . $period['end_date_fr']],
                            ['Salariés actifs', $ind['total_employees']],
                            ['Salariés ayant pointé', $ind['employees_punched_count']],
                            ['Total présences (jours)', $ind['total_presences'] . ' / ' . $ind['total_expected_workdays']],
                            ['Taux de présence global', $ind['presence_rate'] . ' %'],
                            ['Retards constatés', $ind['total_retards_count'] . ' (' . $ind['total_retards_minutes'] . ' min)'],
                            ['Absences justifiées / non justif.', $ind['total_justified_absences'] . ' j / ' . $ind['total_unjustified_absences'] . ' j'],
                            ['Jours de congés', $ind['total_leave_days'] . ' j'],
                            ['Pointages incomplets', $ind['total_incomplete_pointages']],
                            ['Total anomalies', $ind['total_anomalies']],
                            ['Destinataires prévus', implode(', ', $recipients) ?: '<aucun>'],
                            ['Format configuré', $config->report_format],
                        ]
                    );

                    $stats['sent']++;
                    continue;
                }

                if ($sync) {
                    $this->line("  Traitement synchrone en cours...");
                    $result = $service->sendMonthlyReportForCompany(
                        $company,
                        $referenceMonth,
                        $force,
                        $customRecipients,
                        false,
                        $isTargetMonth
                    );

                    if ($result['success']) {
                        $this->info("  ✔ " . $result['message']);
                        $stats['sent']++;
                    } else {
                        $this->warn("  ⚠ " . $result['message']);
                        $stats['skipped']++;
                    }
                } else {
                    $this->line("  Mise en file d'attente du job GenerateMonthlyAttendanceReportJob...");
                    GenerateMonthlyAttendanceReportJob::dispatch(
                        $company,
                        $referenceMonth,
                        $force,
                        $customRecipients,
                        false,
                        $isTargetMonth
                    );
                    $this->info("  ✔ Job ajouté à la file d'attente avec succès.");
                    $stats['queued']++;
                }
            } catch (\Throwable $e) {
                $this->error("  ✖ Erreur lors du traitement : " . $e->getMessage());
                Log::error("Erreur artisan attendance:send-monthly-report pour {$company->name} : " . $e->getMessage(), [
                    'exception' => $e,
                ]);
                $stats['failed']++;
            }
        }

        $this->line("");
        $this->info("===================================================================");
        $this->info("Synthèse : Total: {$stats['total']} | Envoyés: {$stats['sent']} | En file: {$stats['queued']} | Ignorés: {$stats['skipped']} | Échecs: {$stats['failed']}");
        $this->info("===================================================================");

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
