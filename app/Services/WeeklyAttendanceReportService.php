<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\GpsDeclaration;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Employees\Models\Employee;
use Modules\Leaves\Models\Leave;
use Modules\Time\Models\Holiday;
use Modules\Time\Models\Pointeuse;
use Modules\Time\Models\TimeSheet;
use App\Exports\WeeklyAttendanceExport;
use App\Mail\WeeklyAttendanceReportMail;

class WeeklyAttendanceReportService
{
    /**
     * Construit l'ensemble des données du rapport hebdomadaire pour une entreprise donnée.
     *
     * Période couverte : du lundi à 00h00 au vendredi à 18h00 (heure locale de l'entreprise).
     *
     * @param Company $company
     * @param Carbon|string|null $referenceDate
     * @return array
     */
    public function buildReportData(Company $company, $referenceDate = null): array
    {
        $timezone = $this->resolveTimezone($company);

        // Date de référence (vendredi par défaut ou date spécifiée)
        if ($referenceDate instanceof Carbon) {
            $ref = $referenceDate->copy()->setTimezone($timezone);
        } elseif (is_string($referenceDate)) {
            $ref = Carbon::parse($referenceDate, $timezone);
        } else {
            $ref = Carbon::now($timezone);
        }

        // Période couverte : lundi 00h00:00 au vendredi 18h00:00
        $startOfWeek = $ref->copy()->startOfWeek(); // Lundi 00:00:00
        $fridayCutoff = $startOfWeek->copy()->addDays(4)->setTime(18, 0, 0); // Vendredi 18:00:00

        $year = (int) $startOfWeek->format('Y');
        $weekNumber = (int) $startOfWeek->format('W');

        // Récupérer tous les employés actifs rattachés à cette entreprise
        $employees = Employee::where('company_id', $company->id)
            ->where('is_active', 1)
            ->with(['branch', 'department', 'designation'])
            ->orderBy('name')
            ->get();

        // Jours ouvrés du rapport (Lundi à Vendredi = 5 jours)
        $workdays = [];
        for ($i = 0; $i < 5; $i++) {
            $workdays[] = $startOfWeek->copy()->addDays($i);
        }

        $startDateStr = $startOfWeek->toDateString();
        $endDateStr = $fridayCutoff->toDateString();

        // 1. Jours fériés
        $holidayDates = Holiday::datesBetween($company->id, $startOfWeek, $fridayCutoff, false);
        $workedHolidayDates = Holiday::datesBetween($company->id, $startOfWeek, $fridayCutoff, true);

        // 2. Absences justifiées / autorisées (TimeSheet)
        $timesheets = TimeSheet::where('company_id', $company->id)
            ->where('motif_justify', 'Oui')
            ->where(function ($q) use ($startDateStr, $endDateStr) {
                $q->whereBetween('date', [$startDateStr, $endDateStr])
                    ->orWhereBetween('arrival_date', [$startDateStr, $endDateStr])
                    ->orWhere(function ($sub) use ($startDateStr, $endDateStr) {
                        $sub->where('date', '<=', $startDateStr)
                            ->where('arrival_date', '>=', $endDateStr);
                    });
            })
            ->get();

        // 3. Congés (Leave)
        $leaves = Leave::where('company_id', $company->id)
            ->where(function ($q) use ($startDateStr, $endDateStr) {
                $q->whereBetween('start_date', [$startDateStr, $endDateStr])
                    ->orWhereBetween('end_date', [$startDateStr, $endDateStr])
                    ->orWhere(function ($sub) use ($startDateStr, $endDateStr) {
                        $sub->where('start_date', '<=', $startDateStr)
                            ->where('end_date', '>=', $endDateStr);
                    });
            })
            ->where(function ($q) {
                $q->where('status', '!=', 'Rejected')
                    ->orWhereNull('status');
            })
            ->get();

        // Indexation des motifs justifiés par employé et par date
        $justifiedMap = [];
        foreach ($timesheets as $ts) {
            $from = Carbon::parse($ts->date);
            $to = $ts->arrival_date ? Carbon::parse($ts->arrival_date) : $from->copy();
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $dStr = $d->toDateString();
                if ($d->gte($startOfWeek) && $d->lte($fridayCutoff)) {
                    $justifiedMap[$ts->employee_id][$dStr] = $ts->type_permis ?: 'Absence justifiée';
                }
            }
        }

        foreach ($leaves as $lv) {
            $from = Carbon::parse($lv->start_date);
            $to = $lv->end_date ? Carbon::parse($lv->end_date) : $from->copy();
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $dStr = $d->toDateString();
                if ($d->gte($startOfWeek) && $d->lte($fridayCutoff)) {
                    $justifiedMap[$lv->employee_id][$dStr] = $lv->leave_reason ?: 'Congé';
                }
            }
        }

        // 4. Pointages de la semaine
        $allPointages = Pointeuse::where('company_id', $company->id)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('auth_date', [$startDateStr, $endDateStr])
            ->orderBy('auth_date')
            ->orderBy('auth_time')
            ->get()
            ->filter(function ($p) use ($fridayCutoff) {
                // Règle stricte : exclure les pointages du vendredi après 18h00:00
                if ($p->auth_date === $fridayCutoff->toDateString()) {
                    $time = substr((string) $p->auth_time, 0, 8);
                    return $time <= '18:00:00';
                }
                return true;
            });

        $pointagesByEmployee = $allPointages->groupBy('employee_id');

        // 5. Déclarations GPS
        $gpsDeclarations = GpsDeclaration::where('company_id', $company->id)
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->get();

        // 6. Analyse détaillée par employé
        $employeeDetails = [];
        $totalPresences = 0;
        $totalRetardsCount = 0;
        $totalRetardsMinutes = 0;
        $totalEarlyDeparturesCount = 0;
        $lateEmployeeIds = [];
        $employeesWhoPunchedIds = [];
        $totalIncompleteCount = 0;
        $totalJustifiedAbsences = 0;
        $totalUnjustifiedAbsences = 0;

        $unjustifiedAbsencesByEmployee = [];
        $justifiedAbsencesByEmployee = [];
        $retardsList = [];

        // Répartition des méthodes de pointage
        $methodsCount = [
            'qr_code'     => 0,
            'gps'         => 0,
            'biometrique' => 0,
            'autre'       => 0,
        ];

        $totalExpectedWorkdays = 0;

        foreach ($employees as $employee) {
            $branch = $employee->branch;
            $empPointages = $pointagesByEmployee->get($employee->id, collect())->groupBy('auth_date');

            $empPresentDays = 0;
            $empLateDays = 0;
            $empLateMinutes = 0;
            $empJustifiedDays = 0;
            $empUnjustifiedDays = 0;
            $empIncompleteDays = 0;
            $empTotalMinutes = 0;
            $daysDetail = [];

            $empUnjustifiedDaysFormatted = [];
            $empJustifiedDaysFormatted = [];

            foreach ($workdays as $day) {
                $dayStr = $day->toDateString();
                $dayPointages = $empPointages->get($dayStr, collect());

                // Déterminer si le site / entreprise travaille ce jour-là
                $isWorkingDay = $branch ? $branch->worksOn($day) : $company->worksOn($day);
                $isHoliday = in_array($dayStr, $holidayDates, true);
                $isWorkedHoliday = in_array($dayStr, $workedHolidayDates, true);

                if ($isWorkingDay && (!$isHoliday || $isWorkedHoliday)) {
                    $totalExpectedWorkdays++;
                }

                if ($dayPointages->isNotEmpty()) {
                    // Présence confirmée
                    $empPresentDays++;
                    $totalPresences++;
                    $employeesWhoPunchedIds[$employee->id] = true;

                    $entrees = $dayPointages->where('type', 'entree')->sortBy('auth_time');
                    $sorties = $dayPointages->where('type', 'sortie')->sortBy('auth_time');

                    $firstEntree = $entrees->first();
                    $lastSortie = $sorties->last();

                    // Calcul de la méthode et du lieu
                    $displayMethod = 'Bureau · QR';
                    foreach ($dayPointages as $pt) {
                        $method = $this->detectMethod($pt);
                        $methodsCount[$method]++;
                        if ($method === 'gps') {
                            $displayMethod = 'Terrain · GPS';
                        } elseif ($method === 'biometrique') {
                            $displayMethod = 'Bureau · Bio';
                        }
                    }

                    // Calcul retard
                    $isLate = false;
                    $retardMinutes = 0;
                    if ($firstEntree) {
                        $isLate = ($firstEntree->scan_status === 'retard') || (($firstEntree->duree_retard ?? 0) > 0);
                        if ($isLate) {
                            $retardMinutes = (int) round($firstEntree->duree_retard ?? 0);
                            $empLateDays++;
                            $totalRetardsCount++;
                            $empLateMinutes += $retardMinutes;
                            $totalRetardsMinutes += $retardMinutes;
                            $lateEmployeeIds[$employee->id] = true;

                            $retardsList[] = [
                                'employee_name' => $employee->name,
                                'date'          => $day->locale('fr')->isoFormat('ddd D/MM'),
                                'arrival_time'  => substr((string) $firstEntree->auth_time, 0, 5),
                                'minutes'       => $retardMinutes,
                            ];
                        }
                    }

                    // Calcul départ anticipé
                    if ($lastSortie && $lastSortie->scan_status === 'en_avance') {
                        $totalEarlyDeparturesCount++;
                    }

                    // Calcul pointage incomplet
                    $isIncomplete = false;
                    if (($firstEntree && !$lastSortie) || (!$firstEntree && $lastSortie)) {
                        $isIncomplete = true;
                        $empIncompleteDays++;
                        $totalIncompleteCount++;
                    }

                    // Durée de travail
                    $workedHoursFormatted = '—';
                    if ($firstEntree && $lastSortie) {
                        $start = Carbon::parse("{$firstEntree->auth_date} {$firstEntree->auth_time}");
                        $end = Carbon::parse("{$lastSortie->auth_date} {$lastSortie->auth_time}");
                        if ($end->greaterThan($start)) {
                            $diffMin = $start->diffInMinutes($end);
                            $empTotalMinutes += $diffMin;
                            $workedHoursFormatted = sprintf('%02dh%02d', intdiv($diffMin, 60), $diffMin % 60);
                        }
                    }

                    $arrStr = $firstEntree ? substr((string) $firstEntree->auth_time, 0, 5) : '--:--';
                    $depStr = $lastSortie ? substr((string) $lastSortie->auth_time, 0, 5) : '--:--';

                    $daysDetail[$dayStr] = [
                        'date'          => $dayStr,
                        'day_name'      => $day->locale('fr')->isoFormat('dddd'),
                        'status'        => $isLate ? "Retard (+{$retardMinutes}m)" : ($isIncomplete ? "Incomplet" : "À l'heure"),
                        'status_type'   => 'present',
                        'time_range'    => "{$arrStr} – {$depStr}",
                        'arrival_time'  => $arrStr,
                        'departure_time'=> $depStr,
                        'retard_minutes'=> $retardMinutes,
                        'is_late'       => $isLate,
                        'is_incomplete' => $isIncomplete,
                        'worked_time'   => $workedHoursFormatted,
                        'mode'          => $displayMethod,
                        'motif'         => null,
                    ];
                } else {
                    // Aucun pointage pour ce jour
                    $dayAbbr = mb_convert_case($day->locale('fr')->isoFormat('ddd.'), MB_CASE_TITLE, "UTF-8");
                    $dayFullFr = $day->locale('fr')->isoFormat('ddd. D MMM. YYYY');

                    if ($isHoliday && !$isWorkedHoliday) {
                        $status = 'Férié';
                        $statusType = 'holiday';
                        $motif = 'Jour férié';
                    } elseif (!$isWorkingDay) {
                        $status = 'Repos';
                        $statusType = 'closed';
                        $motif = 'Jour non ouvré';
                    } elseif (isset($justifiedMap[$employee->id][$dayStr])) {
                        $status = 'Absence justifiée';
                        $statusType = 'justified_absence';
                        $motif = $justifiedMap[$employee->id][$dayStr];
                        $empJustifiedDays++;
                        $totalJustifiedAbsences++;
                        $empJustifiedDaysFormatted[] = "{$motif} — {$dayFullFr}";
                    } else {
                        // Absence non justifiée sur un jour ouvré
                        $status = 'Absent';
                        $statusType = 'unjustified_absence';
                        $motif = 'Aucun pointage';
                        $empUnjustifiedDays++;
                        $totalUnjustifiedAbsences++;
                        $empUnjustifiedDaysFormatted[] = $dayAbbr;
                    }

                    $daysDetail[$dayStr] = [
                        'date'          => $dayStr,
                        'day_name'      => $day->locale('fr')->isoFormat('dddd'),
                        'status'        => $status,
                        'status_type'   => $statusType,
                        'time_range'    => '--',
                        'arrival_time'  => '--:--',
                        'departure_time'=> '--:--',
                        'retard_minutes'=> 0,
                        'is_late'       => false,
                        'is_incomplete' => false,
                        'worked_time'   => '—',
                        'mode'          => '—',
                        'motif'         => $motif,
                    ];
                }
            }

            if (!empty($empUnjustifiedDaysFormatted)) {
                $unjustifiedAbsencesByEmployee[] = [
                    'employee_name' => $employee->name,
                    'days_string'   => implode(' · ', $empUnjustifiedDaysFormatted),
                ];
            }

            if (!empty($empJustifiedDaysFormatted)) {
                $justifiedAbsencesByEmployee[] = [
                    'employee_name' => $employee->name,
                    'days_string'   => implode('<br>', $empJustifiedDaysFormatted),
                ];
            }

            $totalWorkingHours = sprintf('%02dh%02d', intdiv($empTotalMinutes, 60), $empTotalMinutes % 60);

            $matriculeClean = $employee->employee_id ?: '#EMP' . str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT);
            $departmentName = $employee->department ? $employee->department->name : ($employee->branch ? $employee->branch->name : 'Général');

            $employeeDetails[] = [
                'employee'          => $employee,
                'id'                => $employee->id,
                'matricule'         => $matriculeClean,
                'name'              => $employee->name,
                'department'        => $departmentName,
                'branch'            => $branch ? $branch->name : 'Siège',
                'present_days'      => $empPresentDays,
                'late_days'         => $empLateDays,
                'late_minutes'      => $empLateMinutes,
                'justified_days'    => $empJustifiedDays,
                'unjustified_days'  => $empUnjustifiedDays,
                'incomplete_days'   => $empIncompleteDays,
                'total_hours'       => $totalWorkingHours,
                'total_minutes'     => $empTotalMinutes,
                'days'              => $daysDetail,
            ];
        }

        // 7. Taux de présence & synthèses
        $activeEmployeesCount = $employees->count();
        $employeesWithPunchCount = count($employeesWhoPunchedIds);

        $presenceRate = $totalExpectedWorkdays > 0
            ? round(($totalPresences / $totalExpectedWorkdays) * 100, 1)
            : 0.0;

        $pendingGpsCount = $gpsDeclarations->where('status', 'En attente')->count();
        $rejectedGpsCount = $gpsDeclarations->where('status', 'Rejetée')->count();
        $offsiteAnomalies = $gpsDeclarations->where('is_offsite', true)->whereNull('mission_reference')->count();

        $totalAnomalies = $totalIncompleteCount + $offsiteAnomalies;

        // Total des méthodes
        $totalMethodsScans = array_sum($methodsCount);
        $methodsBreakdown = [];
        if ($totalMethodsScans > 0) {
            foreach ($methodsCount as $key => $count) {
                $methodsBreakdown[$key] = [
                    'count'      => $count,
                    'percentage' => round(($count / $totalMethodsScans) * 100, 1),
                ];
            }
        } else {
            foreach (['qr_code', 'gps', 'biometrique', 'autre'] as $key) {
                $methodsBreakdown[$key] = ['count' => 0, 'percentage' => 0];
            }
        }

        // Destinataires autorisés
        $recipients = $this->resolveRecipients($company);

        // Dates lisibles au format du modèle : "DU 20 AU 24 JUILLET 2026"
        $semaineTitrePeriode = 'SEMAINE DU ' . mb_strtoupper($startOfWeek->locale('fr')->isoFormat('D')) . ' AU ' . mb_strtoupper($fridayCutoff->locale('fr')->isoFormat('D MMMM YYYY'));
        $dateTirage = Carbon::now($timezone)->format('d/m/y [à] H:i:s');

        return [
            'company' => [
                'id'       => $company->id,
                'name'     => $company->name,
                'email'    => $company->email,
                'phone'    => $company->phone,
                'address'  => $company->address,
                'timezone' => $timezone,
            ],
            'period' => [
                'start_date'        => $startDateStr,
                'end_date'          => $endDateStr,
                'start_date_fr'     => $startOfWeek->format('d/m/Y'),
                'end_date_fr'       => $fridayCutoff->format('d/m/Y'),
                'title_period'      => $semaineTitrePeriode,
                'start_formatted'   => $startOfWeek->locale('fr')->isoFormat('dddd D MMMM YYYY [à 00h00]'),
                'end_formatted'     => $fridayCutoff->locale('fr')->isoFormat('dddd D MMMM YYYY [à 18h00]'),
                'range_short'       => mb_convert_case($startOfWeek->locale('fr')->isoFormat('dddd D MMM.'), MB_CASE_TITLE, "UTF-8") . ' — ' . mb_convert_case($fridayCutoff->locale('fr')->isoFormat('dddd D MMM. YYYY'), MB_CASE_TITLE, "UTF-8"),
                'week_number'       => $weekNumber,
                'year'              => $year,
                'date_tirage'       => $dateTirage,
                'generated_at'      => Carbon::now($timezone)->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm'),
            ],
            'indicators' => [
                'total_employees'            => $activeEmployeesCount,
                'employees_punched_count'    => $employeesWithPunchCount,
                'total_presences'            => $totalPresences,
                'presence_rate'              => $presenceRate,
                'late_employees_count'       => count($lateEmployeeIds),
                'total_retards_count'        => $totalRetardsCount,
                'total_retards_minutes'      => $totalRetardsMinutes,
                'total_early_departures'     => $totalEarlyDeparturesCount,
                'total_justified_absences'   => $totalJustifiedAbsences,
                'total_unjustified_absences' => $totalUnjustifiedAbsences,
                'total_absences'             => $totalJustifiedAbsences + $totalUnjustifiedAbsences,
                'total_incomplete_pointages' => $totalIncompleteCount,
                'total_anomalies'            => $totalAnomalies,
                'total_pending_declarations' => $pendingGpsCount,
                'total_rejected_declarations'=> $rejectedGpsCount,
            ],
            'unjustified_absences_list' => $unjustifiedAbsencesByEmployee,
            'justified_absences_list'   => $justifiedAbsencesByEmployee,
            'retards_list'              => $retardsList,
            'methods_breakdown'         => $methodsBreakdown,
            'workdays'                  => $workdays,
            'employees'                 => $employeeDetails,
            'recipients'                => $recipients,
        ];
    }

    /**
     * Détecte la méthode de pointage à partir de l'enregistrement de pointeuse.
     */
    private function detectMethod(Pointeuse $pointage): string
    {
        $device = strtolower((string) ($pointage->name_device ?? ''));

        if (str_contains($device, 'qr')) {
            return 'qr_code';
        }
        if (str_contains($device, 'gps') || str_contains($device, 'géoloc')) {
            return 'gps';
        }
        if (str_contains($device, 'bio') || !empty($pointage->card_no) || !empty($pointage->no_device)) {
            return 'biometrique';
        }

        return 'autre';
    }

    /**
     * Résout le fuseau horaire de l'application / entreprise.
     */
    public function resolveTimezone(Company $company): string
    {
        if (!empty($company->settings['timezone'])) {
            return (string) $company->settings['timezone'];
        }

        return config('app.timezone', 'UTC');
    }

    /**
     * Résout les destinataires autorisés du rapport pour une entreprise.
     *
     * @param Company $company
     * @return array<int, string>
     */
    public function resolveRecipients(Company $company): array
    {
        $recipients = [];

        // 1. Email de l'entreprise si valide
        if (!empty($company->email) && filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = strtolower(trim($company->email));
        }

        // 2. Utilisateurs autorisés rattachés à cette entreprise (Entreprise, RH, Paie)
        $authorizedUsers = User::where('company_id', $company->id)
            ->whereIn('type', ['company', 'hr', 'paie'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        foreach ($authorizedUsers as $user) {
            $email = strtolower(trim($user->email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }

        // 3. Destinataires configurables dans les settings de l'entreprise
        if (!empty($company->settings['weekly_report_recipients']) && is_array($company->settings['weekly_report_recipients'])) {
            foreach ($company->settings['weekly_report_recipients'] as $configuredEmail) {
                $email = strtolower(trim($configuredEmail));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
        }

        return array_values(array_unique($recipients));
    }

    /**
     * Génère le rapport PDF complet via DomPDF selon le modèle exact fourni.
     *
     * @param array $reportData
     * @return string Contenu binaire du fichier PDF
     */
    public function generatePdf(array $reportData): string
    {
        $pdf = Pdf::loadView('reports.attendance.weekly_pdf', $reportData);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'sans-serif',
        ]);

        return $pdf->output();
    }

    /**
     * Génère le rapport Excel via Maatwebsite/Excel.
     *
     * @param array $reportData
     * @return string Contenu binaire du fichier Excel (xlsx)
     */
    public function generateExcel(array $reportData): string
    {
        return Excel::raw(new WeeklyAttendanceExport($reportData), \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Génère et envoie le rapport hebdomadaire par e-mail pour une entreprise.
     *
     * @param Company $company
     * @param Carbon|string|null $referenceDate
     * @param bool $force
     * @param array|null $customRecipients
     * @return array{success: bool, message: string, recipients: array, data: array|null}
     */
    public function sendWeeklyReportForCompany(Company $company, $referenceDate = null, bool $force = false, ?array $customRecipients = null): array
    {
        $reportData = $this->buildReportData($company, $referenceDate);
        $year = $reportData['period']['year'];
        $week = $reportData['period']['week_number'];

        $cacheKey = "weekly_attendance_report_sent_{$company->id}_{$year}_{$week}";

        // Prévention des doublons (désactivée si des destinataires personnalisés sont fournis)
        if (!$force && $customRecipients === null && Cache::has($cacheKey)) {
            Log::info("Rapport hebdomadaire déjà envoyé pour {$company->name} (Semaine {$week}/{$year}). Envoi ignoré.");
            return [
                'success'    => false,
                'message'    => "Rapport déjà envoyé pour la semaine {$week}/{$year}.",
                'recipients' => $reportData['recipients'],
                'data'       => $reportData,
            ];
        }

        $recipients = $customRecipients !== null ? $customRecipients : $reportData['recipients'];
        if (empty($recipients)) {
            Log::warning("Aucun destinataire autorisé trouvé pour {$company->name} (ID: {$company->id}).");
            return [
                'success'    => false,
                'message'    => "Aucun destinataire RH autorisé trouvé.",
                'recipients' => [],
                'data'       => $reportData,
            ];
        }

        // Génération des pièces jointes avec fallback gracieux
        $pdfContent = null;
        try {
            $pdfContent = $this->generatePdf($reportData);
        } catch (\Throwable $e) {
            Log::error("Échec de génération du PDF pour {$company->name} : " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        $excelContent = null;
        try {
            $excelContent = $this->generateExcel($reportData);
        } catch (\Throwable $e) {
            Log::error("Échec de génération de l'Excel pour {$company->name} : " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        try {
            foreach ($recipients as $recipientEmail) {
                $mailable = new WeeklyAttendanceReportMail($reportData, $pdfContent, $excelContent);
                Mail::to($recipientEmail)->send($mailable);
            }

            // Mémorisation de l'envoi pour éviter les doublons (conservé 30 jours)
            Cache::put($cacheKey, [
                'sent_at'    => now()->toDateTimeString(),
                'recipients' => $recipients,
            ], now()->addDays(30));

            Log::info("Rapport hebdomadaire des pointages envoyé avec succès pour {$company->name} aux destinataires : " . implode(', ', $recipients));

            return [
                'success'    => true,
                'message'    => "Rapport envoyé avec succès à " . count($recipients) . " destinataire(s).",
                'recipients' => $recipients,
                'data'       => $reportData,
            ];
        } catch (\Throwable $e) {
            Log::error("Erreur SMTP lors de l'envoi du rapport pour {$company->name} : " . $e->getMessage(), [
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
