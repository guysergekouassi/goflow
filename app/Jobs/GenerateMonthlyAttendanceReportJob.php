<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\MonthlyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyAttendanceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Company $company;
    public ?Carbon $referenceMonth;
    public bool $force;
    public ?array $customRecipients;
    public bool $isManual;
    public bool $isTargetMonth;
    public ?string $formatOverride;
    public ?string $generationKey;

    /**
     * Nombre maximal de tentatives en cas d'échec (ex: saturation temporaire SMTP).
     */
    public int $tries = 3;

    /**
     * Délais d'attente progressifs entre les tentatives (1 min, 5 min, 15 min).
     */
    public array $backoff = [60, 300, 900];

    /**
     * Timeout d'exécution du job en secondes (10 minutes).
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param Carbon|null $referenceMonth
     * @param bool $force
     * @param array|null $customRecipients
     * @param bool $isManual
     * @param bool $isTargetMonth
     */
    public function __construct(
        Company $company,
        ?Carbon $referenceMonth = null,
        bool $force = false,
        ?array $customRecipients = null,
        bool $isManual = false,
        bool $isTargetMonth = false,
        ?string $formatOverride = null,
        ?string $generationKey = null
    ) {
        $this->company = $company;
        $this->referenceMonth = $referenceMonth;
        $this->force = $force;
        $this->customRecipients = $customRecipients;
        $this->isManual = $isManual;
        $this->isTargetMonth = $isTargetMonth;
        $this->formatOverride = $formatOverride;
        $this->generationKey = $generationKey;
    }

    /**
     * Execute the job.
     */
    public function handle(MonthlyAttendanceReportService $service): void
    {
        Log::info("Démarrage du job de génération de rapport mensuel pour l'entreprise {$this->company->name} (ID: {$this->company->id}).");

        $result = $service->sendMonthlyReportForCompany(
            $this->company,
            $this->referenceMonth,
            $this->force,
            $this->customRecipients,
            $this->isManual,
            $this->isTargetMonth,
            $this->formatOverride,
            $this->generationKey
        );

        if ($result['success']) {
            Log::info("Job de rapport mensuel terminé avec succès pour {$this->company->name}.");
        } elseif ($result['retryable'] ?? false) {
            throw new \RuntimeException($result['message']);
        } else {
            Log::warning("Job de rapport mensuel non exécuté ou ignoré pour {$this->company->name} : " . $result['message']);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("Échec définitif du job de rapport mensuel pour {$this->company->name} (ID: {$this->company->id}) : " . $exception->getMessage(), [
            'exception' => $exception,
        ]);
    }
}
