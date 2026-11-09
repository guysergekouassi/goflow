<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMonthlyAttendanceReportJob;
use App\Models\Company;
use App\Models\MonthlyReportHistory;
use App\Services\MonthlyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MonthlyReportController extends Controller
{
    protected MonthlyAttendanceReportService $monthlyReportService;

    public function __construct(MonthlyAttendanceReportService $monthlyReportService)
    {
        $this->monthlyReportService = $monthlyReportService;
    }

    /**
     * Résout l'entreprise de l'utilisateur authentifié avec vérification stricte.
     */
    protected function getCompany(): Company
    {
        $user = Auth::user();
        if ($user && $user->type !== 'company') {
            abort(403, 'Vous n’êtes pas autorisé à consulter les rapports de présence.');
        }

        if (!$user || !$user->company_id) {
            abort(403, 'Aucune entreprise rattachée à cet utilisateur.');
        }

        $company = Company::find($user->company_id);
        if (!$company) {
            abort(404, 'Entreprise non trouvée.');
        }

        return $company;
    }

    /**
     * Liste des MonthlyReportHistory pour l'entreprise courante (paginé, 15 par page).
     */
    public function index()
    {
        $company = $this->getCompany();

        $histories = MonthlyReportHistory::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        $config = $this->monthlyReportService->getOrCreateConfig($company);
        $nextScheduledDate = null;
        if ($config->is_enabled && $config->auto_send_enabled) {
            $timezone = $this->monthlyReportService->resolveTimezone($company);
            $now = Carbon::now($timezone);
            $nextScheduled = $now->copy()
                ->startOfMonth()
                ->day(min((int) $config->send_day, $now->daysInMonth))
                ->setTime((int) $config->send_hour, (int) $config->send_minute);

            if ($nextScheduled->lte($now)) {
                $nextMonth = $now->copy()->addMonthNoOverflow()->startOfMonth();
                $nextScheduled = $nextMonth
                    ->day(min((int) $config->send_day, $nextMonth->daysInMonth))
                    ->setTime((int) $config->send_hour, (int) $config->send_minute);
            }

            $nextScheduledDate = $nextScheduled->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');
        }

        if (request()->wantsJson()) {
            return response()->json([
                'histories' => $histories,
                'config'    => $config,
                'nextScheduledDate' => $nextScheduledDate,
            ]);
        }

        if (view()->exists('company.reports.monthly.index')) {
            return view('company.reports.monthly.index', compact('company', 'histories', 'config', 'nextScheduledDate'));
        }

        return response()->json([
            'histories' => $histories,
            'config'    => $config,
            'nextScheduledDate' => $nextScheduledDate,
        ]);
    }

    /**
     * Afficher la configuration du rapport mensuel pour l'entreprise courante.
     */
    public function settings()
    {
        $company = $this->getCompany();
        $config = $this->monthlyReportService->getOrCreateConfig($company);

        if (request()->wantsJson()) {
            return response()->json([
                'config' => $config,
            ]);
        }

        if (view()->exists('company.reports.monthly.settings')) {
            return view('company.reports.monthly.settings', compact('company', 'config'));
        }

        return response()->json([
            'config' => $config,
        ]);
    }

    /**
     * Valider et sauvegarder la configuration du rapport mensuel.
     */
    public function updateSettings(Request $request)
    {
        $company = $this->getCompany();

        // Support d'une chaîne séparée par des virgules pour le champ recipients
        if ($request->has('recipients') && is_string($request->input('recipients'))) {
            $emails = array_values(array_filter(array_map('trim', explode(',', $request->input('recipients')))));
            $request->merge(['recipients' => $emails]);
        } elseif (is_array($request->input('recipients'))) {
            $emails = array_values(array_filter($request->input('recipients'), fn ($email) => is_string($email) && trim($email) !== ''));
            $emails = array_map('trim', $emails);
            $request->merge(['recipients' => $emails]);
        }

        $autoSend = (bool) $request->input('auto_send_enabled', false);

        $validated = $request->validate([
            'is_enabled'        => 'required|boolean',
            'send_day'          => 'required|integer|min:1|max:31',
            'send_hour'         => 'required|integer|min:0|max:23',
            'send_minute'       => 'nullable|integer|min:0|max:59',
            'recipients'        => [$autoSend ? 'required' : 'nullable', 'array', $autoSend ? 'min:1' : 'nullable'],
            'recipients.*'      => 'required|email',
            'report_format'     => 'required|string|in:pdf,excel,both',
            'auto_send_enabled' => 'required|boolean',
        ], [
            'recipients.required' => 'Au moins une adresse e-mail destinataire est requise lorsque l’envoi automatique est activé.',
            'recipients.min'      => 'Au moins une adresse e-mail destinataire est requise lorsque l’envoi automatique est activé.',
            'recipients.*.email'  => 'Chaque adresse e-mail de destinataire doit être valide.',
            'send_day.min'        => 'Le jour d’envoi doit être compris entre 1 et 31.',
            'send_day.max'        => 'Le jour d’envoi doit être compris entre 1 et 31.',
            'send_hour.min'       => 'L’heure d’envoi doit être comprise entre 0 et 23.',
            'send_hour.max'       => 'L’heure d’envoi doit être comprise entre 0 et 23.',
            'report_format.in'    => 'Le format du rapport doit être PDF, Excel ou les deux.',
        ]);

        $config = $this->monthlyReportService->getOrCreateConfig($company);
        $config->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Configuration du rapport mensuel mise à jour avec succès.',
                'config'  => $config,
            ]);
        }

        return redirect()->back()->with('success', 'Configuration du rapport mensuel mise à jour avec succès.');
    }

    /**
     * Lancer la génération manuelle d'un rapport mensuel (dispatch du job ou synchrone).
     */
    public function generateManual(Request $request)
    {
        $company = $this->getCompany();

        if (!$request->filled('month') && $request->filled('report_month')) {
            $request->merge(['month' => $request->input('report_month')]);
        }

        // Support d'une chaîne séparée par des virgules pour recipients personnalisés
        if ($request->has('recipients') && is_string($request->input('recipients'))) {
            $emails = array_values(array_filter(array_map('trim', explode(',', $request->input('recipients')))));
            $request->merge(['recipients' => $emails]);
        } elseif (is_array($request->input('recipients'))) {
            $emails = array_values(array_filter($request->input('recipients'), fn ($email) => is_string($email) && trim($email) !== ''));
            $request->merge(['recipients' => array_map('trim', $emails)]);
        }

        $request->validate([
            'month'         => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'force'         => 'nullable|boolean',
            'recipients'    => 'nullable|array',
            'recipients.*'  => 'email',
            'sync'          => 'nullable|boolean',
            'test_email'    => 'nullable|email',
            'format'        => 'nullable|string|in:pdf,excel,both',
        ]);

        $referenceMonth = $request->filled('month')
            ? Carbon::createFromFormat('!Y-m', $request->input('month'))->startOfMonth()
            : null;
        $isTargetMonth = $request->filled('month');
        $force = (bool) $request->input('force', true);
        $customRecipients = $request->filled('test_email')
            ? [$request->input('test_email')]
            : $request->input('recipients');
        $sync = (bool) $request->input('sync', false);
        $formatOverride = $request->input('format');
        $generationKey = (string) Str::uuid();

        if ($sync) {
            $result = $this->monthlyReportService->sendMonthlyReportForCompany(
                $company,
                $referenceMonth,
                $force,
                $customRecipients,
                true, // is_manual
                $isTargetMonth,
                $formatOverride,
                $generationKey
            );

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
        }

        GenerateMonthlyAttendanceReportJob::dispatch(
            $company,
            $referenceMonth,
            $force,
            $customRecipients,
            true, // is_manual
            $isTargetMonth,
            $formatOverride,
            $generationKey
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'La génération du rapport mensuel a été programmée en arrière-plan.',
            ]);
        }

        return redirect()->back()->with('success', 'La génération du rapport mensuel a été programmée en arrière-plan.');
    }

    /**
     * Téléchargement générique avec sélecteur de format (?format=pdf|excel).
     */
    public function download(Request $request, MonthlyReportHistory $report)
    {
        $format = strtolower((string) $request->query('format', 'pdf'));
        if (in_array($format, ['excel', 'xlsx'], true)) {
            return $this->downloadExcel($report);
        }

        return $this->downloadPdf($report);
    }

    /**
     * Télécharger le PDF d'un rapport mensuel historique (vérification stricte de company_id).
     */
    public function downloadPdf(MonthlyReportHistory $report)
    {
        $company = $this->getCompany();
        abort_if((int) $report->company_id !== (int) $company->id, 403, 'Accès non autorisé.');
        abort_unless($report->generation_status === 'success' && in_array($report->format, ['pdf', 'both'], true), 404, 'Rapport PDF non disponible.');

        if ($report->file_path_pdf && Storage::disk('local')->exists($report->file_path_pdf)) {
            return Storage::disk('local')->download($report->file_path_pdf);
        }

        // Régénération à la volée si le fichier sur disque est introuvable
        $refDate = Carbon::parse($report->period_start)->startOfMonth();
        $reportData = $this->monthlyReportService->buildReportData($company, $refDate, true);
        $pdfContent = $this->monthlyReportService->generatePdf($reportData);

        $slug = Str::slug($company->name ?: 'entreprise');
        $fileName = "rapport_mensuel_{$report->report_month}_{$slug}.pdf";

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Télécharger l'Excel d'un rapport mensuel historique (vérification stricte de company_id).
     */
    public function downloadExcel(MonthlyReportHistory $report)
    {
        $company = $this->getCompany();
        abort_if((int) $report->company_id !== (int) $company->id, 403, 'Accès non autorisé.');
        abort_unless($report->generation_status === 'success' && in_array($report->format, ['excel', 'both'], true), 404, 'Rapport Excel non disponible.');

        if ($report->file_path_excel && Storage::disk('local')->exists($report->file_path_excel)) {
            return Storage::disk('local')->download($report->file_path_excel);
        }

        // Régénération à la volée si le fichier sur disque est introuvable
        $refDate = Carbon::parse($report->period_start)->startOfMonth();
        $reportData = $this->monthlyReportService->buildReportData($company, $refDate, true);
        $excelContent = $this->monthlyReportService->generateExcel($reportData);

        $slug = Str::slug($company->name ?: 'entreprise');
        $fileName = "rapport_mensuel_{$report->report_month}_{$slug}.xlsx";

        return response($excelContent, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Supprimer un rapport historique (vérification stricte de company_id).
     */
    public function destroy(MonthlyReportHistory $report)
    {
        $company = $this->getCompany();
        abort_if((int) $report->company_id !== (int) $company->id, 403, 'Accès non autorisé.');

        if ($report->file_path_pdf && Storage::disk('local')->exists($report->file_path_pdf)) {
            Storage::disk('local')->delete($report->file_path_pdf);
        }
        if ($report->file_path_excel && Storage::disk('local')->exists($report->file_path_excel)) {
            Storage::disk('local')->delete($report->file_path_excel);
        }

        $report->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Rapport supprimé avec succès.',
            ]);
        }

        return redirect()->back()->with('success', 'Rapport supprimé avec succès.');
    }
}
