<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyAttendanceReportJob;
use App\Models\Company;
use App\Services\WeeklyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeeklyAttendanceReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:send-weekly-report
                            {--company= : ID ou nom de l\'entreprise spécifique}
                            {--to= : Adresse e-mail personnalisée pour un envoi de test direct}
                            {--date= : Date personnalisée du vendredi de référence (format YYYY-MM-DD)}
                            {--force : Forcer l\'envoi même si le rapport a déjà été expédié cette semaine}
                            {--sync : Exécuter l\'envoi en mode synchrone (sans passer par la file d\'attente)}
                            {--dry-run : Générer et afficher les données sans envoyer d\'e-mail}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère et expédie le rapport hebdomadaire des pointages du lundi au vendredi à 18h aux responsables RH';

    /**
     * Execute the console command.
     */
    public function handle(WeeklyAttendanceReportService $service): int
    {
        $companyFilter = $this->option('company');
        $customTo = $this->option('to');
        $customDate = $this->option('date');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync') || !empty($customTo);
        $dryRun = (bool) $this->option('dry-run');

        $this->info("===================================================================");
        $this->info("   GP FLOW — Génération & Envoi du Rapport Hebdomadaire RH        ");
        $this->info("===================================================================");

        // Sélection des entreprises ciblées
        $query = Company::query();
        if ($companyFilter) {
            if (is_numeric($companyFilter)) {
                $query->where('id', $companyFilter);
            } else {
                $query->where('name', 'like', "%{$companyFilter}%");
            }
        } else {
            $query->where('is_active', true);
            // Si une adresse de test unique est spécifiée sans entreprise, on cible la première entreprise active
            if ($customTo) {
                $query->limit(1);
            }
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            $this->warn("Aucune entreprise active trouvée correspondant aux critères.");
            return Command::SUCCESS;
        }

        $this->info("Entreprises à traiter : " . $companies->count());
        $referenceDate = $customDate ? Carbon::parse($customDate) : null;

        $stats = [
            'total'   => $companies->count(),
            'sent'    => 0,
            'queued'  => 0,
            'skipped' => 0,
            'failed'  => 0,
        ];

        foreach ($companies as $company) {
            $this->line("");
            $this->line("<comment>► Traitement de : {$company->name} (ID: {$company->id})</comment>");

            try {
                $customRecipients = $customTo ? array_values(array_filter(array_map('trim', explode(',', $customTo)))) : null;

                if ($dryRun) {
                    $this->info("  [Mode Simulation — Dry Run]");
                    $reportData = $service->buildReportData($company, $referenceDate);
                    $period = $reportData['period'];
                    $ind = $reportData['indicators'];
                    $recipients = $customRecipients ?? $reportData['recipients'];

                    $this->table(
                        ['Paramètre', 'Valeur'],
                        [
                            ['Période', "{$period['start_formatted']} au {$period['end_formatted']}"],
                            ['Fuseau horaire', $company->settings['timezone'] ?? config('app.timezone', 'UTC')],
                            ['Employés actifs', $ind['total_employees']],
                            ['Présences totales', $ind['total_presences']],
                            ['Employés en retard', $ind['late_employees_count']],
                            ['Cumul des retards', "{$ind['total_retards_minutes']} min"],
                            ['Absences justifiées', $ind['total_justified_absences']],
                            ['Absences non justifiées', $ind['total_unjustified_absences']],
                            ['Pointages incomplets', $ind['total_incomplete_pointages']],
                            ['Anomalies constatées', $ind['total_anomalies']],
                            ['Destinataires autorisés', implode(', ', $recipients) ?: '<aucun>'],
                        ]
                    );

                    $stats['sent']++;
                    continue;
                }

                if ($sync) {
                    $this->line("  Exécution synchrone...");
                    $result = $service->sendWeeklyReportForCompany($company, $referenceDate, $force, $customRecipients);

                    if ($result['success']) {
                        $this->info("  ✔ Succès : " . $result['message']);
                        $stats['sent']++;
                    } else {
                        $this->warn("  ➜ Non envoyé : " . $result['message']);
                        $stats['skipped']++;
                    }
                } else {
                    $this->line("  Mise en file d'attente du job asynchrone...");
                    SendWeeklyAttendanceReportJob::dispatch($company, $referenceDate, $force, $customRecipients);
                    $this->info("  ✔ Job expédié en file d'attente avec succès.");
                    $stats['queued']++;
                }
            } catch (\Throwable $e) {
                $this->error("  ✖ Erreur pour {$company->name} : " . $e->getMessage());
                Log::error("Erreur commande attendance:send-weekly-report pour {$company->name} : " . $e->getMessage(), [
                    'exception' => $e,
                ]);
                $stats['failed']++;
            }
        }

        $this->line("");
        $this->info("-------------------------------------------------------------------");
        $this->info("Bilan : Total: {$stats['total']} | Envoyés: {$stats['sent']} | En file: {$stats['queued']} | Ignorés: {$stats['skipped']} | Échecs: {$stats['failed']}");
        $this->info("-------------------------------------------------------------------");

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
