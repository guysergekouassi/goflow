<?php

namespace App\Console\Commands;

use App\Mail\WeeklyAttendanceReportMail;
use App\Models\Company;
use App\Services\WeeklyAttendanceReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Envoie chaque vendredi le rapport de présence de la semaine à l'admin de
 * chaque entreprise (planifié dans routes/console.php).
 *
 * Envoi synchrone : le rapport part même si aucun worker de file d'attente
 * ne tourne sur le serveur.
 */
class SendWeeklyAttendanceReport extends Command
{
    protected $signature = 'rapports:absences-hebdo
        {--company= : Seulement cette entreprise (id)}
        {--to= : Envoyer à cette adresse au lieu de l\'admin (test)}
        {--date= : Jour de référence AAAA-MM-JJ (par défaut aujourd\'hui)}
        {--dry-run : Générer le PDF dans le stockage local (rapports/) sans envoyer de mail}';

    protected $description = 'Envoie le rapport hebdomadaire de présence (PDF) à l\'admin de chaque entreprise';

    public function handle(WeeklyAttendanceReport $service): int
    {
        $reference = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $companies = Company::with('userName')
            ->when($this->option('company'), fn ($q, $id) => $q->where('id', $id))
            ->get()
            ->filter(fn (Company $c) => $this->option('company') || $this->estActive($c));

        $envoyes = 0;

        foreach ($companies as $company) {
            $destinataire = $this->option('to') ?: ($company->userName->email ?? $company->email);

            if (!$destinataire) {
                $this->warn("{$company->name} : aucune adresse e-mail d'admin, rapport ignoré.");
                continue;
            }

            try {
                $rapport = $service->build($company, $reference);

                if ($rapport['stats']['effectif'] === 0) {
                    $this->line("{$company->name} : aucun employé actif, rapport ignoré.");
                    continue;
                }

                $pdf = $service->pdf($rapport);
                $mail = new WeeklyAttendanceReportMail($rapport, $pdf);

                if ($this->option('dry-run')) {
                    $chemin = 'rapports/' . $company->id . '-' . $mail->nomFichier();
                    Storage::disk('local')->put($chemin, $pdf);
                    $this->info("{$company->name} : PDF généré dans " . Storage::disk('local')->path($chemin) . ' (non envoyé).');
                    continue;
                }

                Mail::to($destinataire)->send($mail);
                $envoyes++;
                $this->info("{$company->name} : rapport envoyé à {$destinataire}.");
            } catch (\Throwable $e) {
                Log::error('Rapport hebdomadaire de présence : échec', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("{$company->name} : échec — {$e->getMessage()}");
            }
        }

        $this->info("{$envoyes} rapport(s) envoyé(s).");

        return self::SUCCESS;
    }

    /**
     * Entreprise dont l'abonnement est en cours et dont le compte admin est actif.
     */
    private function estActive(Company $company): bool
    {
        return !$company->isSubscriptionExpired()
            && !in_array($company->subscription_status, ['suspended', 'cancelled', 'expired', 'pending'], true)
            && ($company->userName->is_active ?? false);
    }
}
