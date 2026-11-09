<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\WeeklyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWeeklyAttendanceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Company $company;
    public $referenceDate;
    public bool $force;
    public ?array $customRecipients;

    /**
     * Nombre maximal de tentatives en cas d'échec (ex: échec SMTP temporaire).
     */
    public int $tries = 3;

    /**
     * Délais entre les tentatives en secondes (1 min, 5 min, 15 min).
     */
    public array $backoff = [60, 300, 900];

    /**
     * Timeout du job en secondes (5 minutes max pour la génération de PDF/Excel).
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param Carbon|string|null $referenceDate
     * @param bool $force
     * @param array|null $customRecipients
     */
    public function __construct(Company $company, $referenceDate = null, bool $force = false, ?array $customRecipients = null)
    {
        $this->company = $company;
        $this->referenceDate = $referenceDate;
        $this->force = $force;
        $this->customRecipients = $customRecipients;
    }

    /**
     * Execute the job.
     */
    public function handle(WeeklyAttendanceReportService $service): void
    {
        Log::info("Démarrage du job d'envoi de rapport hebdomadaire pour l'entreprise {$this->company->name} (ID: {$this->company->id}).");

        $result = $service->sendWeeklyReportForCompany($this->company, $this->referenceDate, $this->force, $this->customRecipients);

        if ($result['success']) {
            Log::info("Job terminé avec succès pour {$this->company->name}.");
        } else {
            Log::warning("Job non exécuté ou ignoré pour {$this->company->name} : " . $result['message']);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("Échec définitif du job d'envoi du rapport hebdomadaire pour {$this->company->name} (ID: {$this->company->id}) : " . $exception->getMessage(), [
            'exception' => $exception,
        ]);
    }
}
