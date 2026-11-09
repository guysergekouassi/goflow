<?php

namespace App\Services;

use App\Exports\MonthlyAttendanceExport;
use App\Mail\MonthlyAttendanceReportMail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GpsDeclaration;
use App\Models\MonthlyReportConfig;
use App\Models\MonthlyReportHistory;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Employees\Models\Employee;
use Modules\Leaves\Models\Leave;
use Modules\Time\Models\Holiday;
use Modules\Time\Models\Pointeuse;
use Modules\Time\Models\TimeSheet;

class MonthlyAttendanceReportService
{
    /**
     * Construit l'ensemble des données du rapport mensuel pour une entreprise donnée.
     *
     * Période couverte : startOfMonth -> endOfMonth du mois précédent (ou mois cible).
     *
     * @param Company $company
     * @param Carbon|string|null $referenceMonth
     * @param bool $isTargetMonth Indique si $referenceMonth est directement le mois du rapport
     * @return array
     */
    public function buildReportData(Company $company, $referenceMonth = null, bool $isTargetMonth = false): array
    {
        $timezone = $this->resolveTimezone($company);

        // Détermination du mois de référence
        $parsedDate = $this->resolveReferenceDate($referenceMonth, $timezone);

        // Si $isTargetMonth est true, $parsedDate est directement le mois à couvrir,
        // sinon le rapport couvre le mois précédent par rapport à $parsedDate (ou now()).
        $targetMonth = $isTargetMonth ? $parsedDate->copy() : $parsedDate->copy()->subMonthNoOverflow();

        $startOfMonth = $targetMonth->copy()->startOfMonth()->startOfDay();
        $endOfMonth = $targetMonth->copy()->endOfMonth()->endOfDay();

        $year = (int) $startOfMonth->format('Y');
        $monthNumber = (int) $startOfMonth->format('m');
        $reportMonth = $startOfMonth->format('Y-m'); // Ex: 2026-10
        $monthNameFr = mb_convert_case($startOfMonth->locale('fr')->isoFormat('MMMM'), MB_CASE_TITLE, 'UTF-8');

        $startDateStr = $startOfMonth->toDateString();
        $endDateStr = $endOfMonth->toDateString();

        // 1. Récupérer tous les employés actifs rattachés à cette entreprise
        $employees = Employee::where('company_id', $company->id)
            ->where('is_active', 1)
            ->with(['branch', 'department', 'designation'])
            ->orderBy('name')
            ->get();

        // 2. Jours du mois
        $periodDays = [];
        for ($d = $startOfMonth->copy(); $d->lte($endOfMonth); $d->addDay()) {
            $periodDays[] = $d->copy();
        }

        // 3. Jours fériés
        $holidayDates = Holiday::datesBetween($company->id, $startOfMonth, $endOfMonth, false);
        $workedHolidayDates = Holiday::datesBetween($company->id, $startOfMonth, $endOfMonth, true);

        // 4. Absences justifiées / autorisées (TimeSheet)
        $timesheets = TimeSheet::where('company_id', $company->id)
            ->where('motif_justify', 'Oui')
            ->where('statut', 'approved')
            ->where(function ($q) use ($startDateStr, $endDateStr) {
                $q->whereBetween('date', [$startDateStr, $endDateStr])
                    ->orWhereBetween('arrival_date', [$startDateStr, $endDateStr])
                    ->orWhere(function ($sub) use ($startDateStr, $endDateStr) {
                        $sub->where('date', '<=', $startDateStr)
                            ->where('arrival_date', '>=', $endDateStr);
                    });
            })
            ->get();

        // 5. Congés (Leave)
        $leaves = Leave::where('company_id', $company->id)
            ->where(function ($q) use ($startDateStr, $endDateStr) {
                $q->whereBetween('start_date', [$startDateStr, $endDateStr])
                    ->orWhereBetween('end_date', [$startDateStr, $endDateStr])
                    ->orWhere(function ($sub) use ($startDateStr, $endDateStr) {
                        $sub->where('start_date', '<=', $startDateStr)
                            ->where('end_date', '>=', $endDateStr);
                    });
            })
            ->whereIn('status', ['Approuvé', 'Démarré', 'Terminé'])
            ->get();

        // Indexation des motifs justifiés et congés par employé et par date
        $justifiedMap = [];
        $leaveMap = [];

        foreach ($timesheets as $ts) {
            $from = Carbon::parse($ts->date);
            $to = $ts->arrival_date ? Carbon::parse($ts->arrival_date) : $from->copy();
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $dStr = $d->toDateString();
                if ($d->gte($startOfMonth) && $d->lte($endOfMonth)) {
                    $justifiedMap[$ts->employee_id][$dStr] = $ts->type_permis ?: 'Absence justifiée';
                }
            }
        }

        foreach ($leaves as $lv) {
            $from = Carbon::parse($lv->start_date);
            $to = $lv->end_date ? Carbon::parse($lv->end_date) : $from->copy();
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $dStr = $d->toDateString();
                if ($d->gte($startOfMonth) && $d->lte($endOfMonth)) {
                    $reason = $lv->leave_reason ?: 'Congé';
                    $justifiedMap[$lv->employee_id][$dStr] = $reason;
                    $leaveMap[$lv->employee_id][$dStr] = $reason;
                }
            }
        }

        // 6. Pointages du mois
        $allPointages = Pointeuse::where('company_id', $company->id)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('auth_date', [$startDateStr, $endDateStr])
            ->orderBy('auth_date')
            ->orderBy('auth_time')
            ->get();

        $pointagesByEmployee = $allPointages->groupBy('employee_id');

        // 7. Déclarations GPS
        $gpsDeclarations = GpsDeclaration::where('company_id', $company->id)
            ->whereBetween('date', [$startDateStr, $endDateStr])
            ->get();

        // 8. Analyse détaillée par employé
        $employeeDetails = [];
        $anomaliesList = [];
        $totalPresences = 0;
        $totalExpectedPresences = 0;
        $totalRetardsCount = 0;
        $totalRetardsMinutes = 0;
        $totalEarlyDeparturesCount = 0;
        $lateEmployeeIds = [];
        $employeesWhoPunchedIds = [];
        $totalIncompleteCount = 0;
        $totalJustifiedAbsences = 0;
        $totalUnjustifiedAbsences = 0;
        $totalLeaveDays = 0;

        $retardsList = [];

        // Répartition des méthodes de pointage
        $methodsCount = [
            'qr_code'     => 0,
            'gps'         => 0,
            'biometrique' => 0,
            'autre'       => 0,
        ];

        $totalExpectedWorkdays = 0;

        // Regroupement par département
        $departmentsMap = [];

        foreach ($employees as $employee) {
            $matriculeClean = $employee->employee_id ?: '#EMP' . str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT);
            $branch = $employee->branch;
            $department = $employee->department;
            if ($branch && (int) $branch->company_id !== (int) $company->id) {
                $branch = null;
            }
            if ($department && (
                ($department->company_id !== null && (int) $department->company_id !== (int) $company->id)
                || ($department->company_id === null && (!$branch || (int) $department->branch_id !== (int) $branch->id))
            )) {
                $department = null;
            }
            $deptKey = $department ? (string) $department->id : 'none';
            $deptName = $department ? $department->name : ($branch ? $branch->name : 'Général');

            if (!isset($departmentsMap[$deptKey])) {
                $departmentsMap[$deptKey] = [
                    'id'                     => $deptKey,
                    'name'                   => $deptName,
                    'employees_count'        => 0,
                    'expected_workdays'      => 0,
                    'expected_present_days'  => 0,
                    'present_days'           => 0,
                    'justified_absences'     => 0,
                    'unjustified_absences'   => 0,
                    'total_absences'         => 0,
                    'leave_days'             => 0,
                    'late_days'              => 0,
                    'late_minutes'           => 0,
                    'incomplete_days'        => 0,
                    'worked_minutes'         => 0,
                    'presence_rate'          => 0.0,
                ];
            }
            $departmentsMap[$deptKey]['employees_count']++;

            $empPointages = $pointagesByEmployee->get($employee->id, collect())->groupBy('auth_date');

            $empPresentDays = 0;
            $empLateDays = 0;
            $empLateMinutes = 0;
            $empJustifiedDays = 0;
            $empUnjustifiedDays = 0;
            $empLeaveDays = 0;
            $empIncompleteDays = 0;
            $empTotalMinutes = 0;
            $empExpectedWorkdays = 0;
            $empExpectedPresentDays = 0;
            $daysDetail = [];

            foreach ($periodDays as $day) {
                $dayStr = $day->toDateString();
                $dayPointages = $empPointages->get($dayStr, collect());

                // Déterminer si le site / entreprise travaille ce jour-là
                $isWorkingDay = $branch ? $branch->worksOn($day) : $company->worksOn($day);
                $isHoliday = in_array($dayStr, $holidayDates, true);
                $isWorkedHoliday = in_array($dayStr, $workedHolidayDates, true);

                $isExpectedWorkday = $isWorkingDay && (!$isHoliday || $isWorkedHoliday);
                if ($isExpectedWorkday) {
                    $totalExpectedWorkdays++;
                    $empExpectedWorkdays++;
                    $departmentsMap[$deptKey]['expected_workdays']++;
                }

                if ($dayPointages->isNotEmpty()) {
                    // Présence confirmée
                    $empPresentDays++;
                    $totalPresences++;
                    $departmentsMap[$deptKey]['present_days']++;
                    $employeesWhoPunchedIds[$employee->id] = true;
                    if ($isExpectedWorkday) {
                        $totalExpectedPresences++;
                        $empExpectedPresentDays++;
                        $departmentsMap[$deptKey]['expected_present_days']++;
                    }

                    $entrees = $dayPointages->where('type', 'entree')->sortBy('auth_time');
                    $sorties = $dayPointages->where('type', 'sortie')->sortBy('auth_time');

                    $firstEntree = $entrees->first();
                    $lastSortie = $sorties->last();

                    // Détection méthode
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
                            $departmentsMap[$deptKey]['late_days']++;
                            $empLateMinutes += $retardMinutes;
                            $totalRetardsMinutes += $retardMinutes;
                            $departmentsMap[$deptKey]['late_minutes'] += $retardMinutes;
                            $lateEmployeeIds[$employee->id] = true;

                            $retardsList[] = [
                                'employee_name' => $employee->name,
                                'department'    => $deptName,
                                'date'          => $day->locale('fr')->isoFormat('ddd D/MM'),
                                'arrival_time'  => substr((string) $firstEntree->auth_time, 0, 5),
                                'minutes'       => $retardMinutes,
                            ];

                            if ($retardMinutes >= 15) {
                                $anomaliesList[] = [
                                    'date'          => $day->format('d/m/Y'),
                                    'raw_date'      => $dayStr,
                                    'employee_name' => $employee->name,
                                    'matricule'     => $matriculeClean,
                                    'department'    => $deptName,
                                    'type'          => 'Retard important',
                                    'description'   => "Retard de {$retardMinutes} min (Arrivée constatée à " . substr((string) $firstEntree->auth_time, 0, 5) . ")",
                                ];
                            }
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
                        $departmentsMap[$deptKey]['incomplete_days']++;

                        $anomaliesList[] = [
                            'date'          => $day->format('d/m/Y'),
                            'raw_date'      => $dayStr,
                            'employee_name' => $employee->name,
                            'matricule'     => $matriculeClean,
                            'department'    => $deptName,
                            'type'          => 'Pointage incomplet',
                            'description'   => ($firstEntree && !$lastSortie)
                                ? "Entrée enregistrée à " . substr((string) $firstEntree->auth_time, 0, 5) . " sans pointage de sortie"
                                : "Sortie enregistrée à " . substr((string) $lastSortie->auth_time, 0, 5) . " sans pointage d'entrée",
                        ];
                    }

                    // Durée de travail
                    $workedHoursFormatted = '—';
                    if ($firstEntree && $lastSortie) {
                        $start = Carbon::parse("{$firstEntree->auth_date} {$firstEntree->auth_time}");
                        $end = Carbon::parse("{$lastSortie->auth_date} {$lastSortie->auth_time}");
                        if ($end->greaterThan($start)) {
                            $diffMin = $start->diffInMinutes($end);
                            $empTotalMinutes += $diffMin;
                            $departmentsMap[$deptKey]['worked_minutes'] += $diffMin;
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
                    // Aucun pointage
                    if ($isHoliday && !$isWorkedHoliday) {
                        $status = 'Férié';
                        $statusType = 'holiday';
                        $motif = 'Jour férié';
                    } elseif (!$isWorkingDay) {
                        $status = 'Repos';
                        $statusType = 'closed';
                        $motif = 'Jour non ouvré';
                    } elseif (isset($leaveMap[$employee->id][$dayStr])) {
                        $status = 'Congé';
                        $statusType = 'leave';
                        $motif = $leaveMap[$employee->id][$dayStr];
                        $empLeaveDays++;
                        $totalLeaveDays++;
                        $departmentsMap[$deptKey]['leave_days']++;
                    } elseif (isset($justifiedMap[$employee->id][$dayStr])) {
                        $status = 'Absence justifiée';
                        $statusType = 'justified_absence';
                        $motif = $justifiedMap[$employee->id][$dayStr];
                        $empJustifiedDays++;
                        $totalJustifiedAbsences++;
                        $departmentsMap[$deptKey]['justified_absences']++;
                    } else {
                        $status = 'Absent';
                        $statusType = 'unjustified_absence';
                        $motif = 'Aucun pointage';
                        $empUnjustifiedDays++;
                        $totalUnjustifiedAbsences++;
                        $departmentsMap[$deptKey]['unjustified_absences']++;
                    }

                    $daysDetail[$dayStr] = [
                        'date'          => $dayStr,
                        'day_name'      => $day->locale('fr')->isoFormat('dddd'),
                        'status'        => $status,
                        'status_type'   => $statusType,
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

            $totalWorkingHours = sprintf('%02dh%02d', intdiv($empTotalMinutes, 60), $empTotalMinutes % 60);
            // $matriculeClean already set at the top of this loop iteration (line ~180)

            $empPresenceRate = $empExpectedWorkdays > 0
                ? round(($empExpectedPresentDays / $empExpectedWorkdays) * 100, 1)
                : 0.0;

            $employeeDetails[] = [
                'employee'          => $employee,
                'id'                => $employee->id,
                'matricule'         => $matriculeClean,
                'name'              => $employee->name,
                'department'        => $deptName,
                'branch'            => $branch ? $branch->name : 'Siège',
                'expected_workdays' => $empExpectedWorkdays,
                'present_days'      => $empPresentDays,
                'presence_days'     => $empPresentDays,
                'presence_rate'     => $empPresenceRate,
                'late_days'         => $empLateDays,
                'retards'           => $empLateDays,
                'late_minutes'      => $empLateMinutes,
                'justified_days'    => $empJustifiedDays,
                'unjustified_days'  => $empUnjustifiedDays,
                'absence_days'      => $empJustifiedDays + $empUnjustifiedDays,
                'leave_days'        => $empLeaveDays,
                'incomplete_days'   => $empIncompleteDays,
                'total_hours'       => $totalWorkingHours,
                'total_minutes'     => $empTotalMinutes,
                'days'              => $daysDetail,
            ];
        }

        // Ajout des déclarations GPS hors périmètre sans référence de mission
        foreach ($gpsDeclarations->where('is_offsite', true)->whereNull('mission_reference') as $decl) {
            $emp = $employees->firstWhere('id', $decl->employee_id);
            $empName = $emp ? $emp->name : ('Employé #' . $decl->employee_id);
            $empMatricule = $emp ? ($emp->employee_id ?: '#EMP' . str_pad((string) $emp->id, 4, '0', STR_PAD_LEFT)) : '—';
            $declDate = Carbon::parse($decl->date);
            $anomaliesList[] = [
                'date'          => $declDate->format('d/m/Y'),
                'raw_date'      => $decl->date,
                'employee_name' => $empName,
                'matricule'     => $empMatricule,
                'department'    => $emp && $emp->department ? $emp->department->name : 'Général',
                'type'          => 'Pointage GPS hors zone',
                'description'   => "Pointage terrain hors zone sans ordre de mission (Statut: " . ($decl->status ?? 'En attente') . ")",
            ];
        }

        // Tri chronologique des anomalies
        usort($anomaliesList, fn ($a, $b) => strcmp($a['raw_date'] ?? '', $b['raw_date'] ?? ''));

        // Finalisation des statistiques par département
        $departmentsSummary = [];
        foreach ($departmentsMap as $d) {
            $d['total_absences'] = $d['justified_absences'] + $d['unjustified_absences'];
            $d['presence_rate'] = $d['expected_workdays'] > 0
                ? round(($d['expected_present_days'] / $d['expected_workdays']) * 100, 1)
                : 0.0;
            $d['total_hours'] = sprintf('%02dh%02d', intdiv($d['worked_minutes'], 60), $d['worked_minutes'] % 60);

            // Aliases pour conformité exacte avec les templates PDF et Mail
            $d['employee_count'] = $d['employees_count'];
            $d['presence_days']  = $d['present_days'];
            $d['absence_days']   = $d['total_absences'];
            $d['retards']        = $d['late_days'];
            $d['incomplete']     = $d['incomplete_days'];

            $departmentsSummary[] = $d;
        }

        // Tri des départements par nom
        usort($departmentsSummary, fn ($a, $b) => strcmp($a['name'], $b['name']));

        // 9. Synthèses et indicateurs globaux
        $activeEmployeesCount = $employees->count();
        $employeesWithPunchCount = count($employeesWhoPunchedIds);

        $presenceRate = $totalExpectedWorkdays > 0
            ? round(($totalExpectedPresences / $totalExpectedWorkdays) * 100, 1)
            : 0.0;

        $pendingGpsCount = $gpsDeclarations->where('status', 'En attente')->count();
        $rejectedGpsCount = $gpsDeclarations->where('status', 'Rejetée')->count();
        $offsiteAnomalies = $gpsDeclarations->where('is_offsite', true)->whereNull('mission_reference')->count();
        $totalAnomalies = $totalIncompleteCount + $offsiteAnomalies;

        // Répartition des méthodes
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

        // Destinataires
        $recipients = $this->resolveRecipients($company);

        $moisTitrePeriode = 'MOIS DE ' . mb_strtoupper($monthNameFr . ' ' . $year);
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
                'start_date_fr'     => $startOfMonth->format('d/m/Y'),
                'end_date_fr'       => $endOfMonth->format('d/m/Y'),
                'report_month'      => $reportMonth,
                'month_name_fr'     => $monthNameFr,
                'month_label'       => $monthNameFr,
                'month_number'      => $monthNumber,
                'year'              => $year,
                'title_period'      => $moisTitrePeriode,
                'start_formatted'   => $startOfMonth->locale('fr')->isoFormat('dddd D MMMM YYYY'),
                'end_formatted'     => $endOfMonth->locale('fr')->isoFormat('dddd D MMMM YYYY'),
                'range_short'       => $startOfMonth->format('d/m/Y') . ' — ' . $endOfMonth->format('d/m/Y'),
                'date_tirage'       => $dateTirage,
                'generated_at'      => Carbon::now($timezone)->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm'),
            ],
            'indicators' => [
                'total_employees'            => $activeEmployeesCount,
                'employees_punched_count'    => $employeesWithPunchCount,
                'total_expected_workdays'    => $totalExpectedWorkdays,
                'total_presences'            => $totalPresences,
                'total_presence_days'        => $totalPresences,
                'presence_rate'              => $presenceRate,
                'late_employees_count'       => count($lateEmployeeIds),
                'total_retards_count'        => $totalRetardsCount,
                'total_retards'              => $totalRetardsCount,
                'total_retards_minutes'      => $totalRetardsMinutes,
                'total_early_departures'     => $totalEarlyDeparturesCount,
                'total_justified_absences'   => $totalJustifiedAbsences,
                'total_unjustified_absences' => $totalUnjustifiedAbsences,
                'total_absences'             => $totalJustifiedAbsences + $totalUnjustifiedAbsences,
                'total_absence_days'         => $totalJustifiedAbsences + $totalUnjustifiedAbsences,
                'total_leave_days'           => $totalLeaveDays,
                'total_leaves'               => $totalLeaveDays,
                'total_incomplete_pointages' => $totalIncompleteCount,
                'total_incomplete'           => $totalIncompleteCount,
                'total_anomalies'            => $totalAnomalies,
                'total_pending_declarations' => $pendingGpsCount,
                'total_rejected_declarations'=> $rejectedGpsCount,
            ],
            'departments'       => $departmentsSummary,
            'anomalies'         => $anomaliesList,
            'retards_list'      => $retardsList,
            'methods_breakdown' => $methodsBreakdown,
            'period_days'       => $periodDays,
            'employees'         => $employeeDetails,
            'recipients'        => $recipients,
        ];
    }

    /**
     * Détecte la méthode de pointage à partir de l'enregistrement de pointeuse.
     */
    public function detectMethod(Pointeuse $pointage): string
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
     * Résout le fuseau horaire de l'entreprise.
     */
    public function resolveTimezone(Company $company): string
    {
        if (!empty($company->settings['timezone'])) {
            return (string) $company->settings['timezone'];
        }

        return config('app.timezone', 'UTC');
    }

    private function resolveReferenceDate($referenceDate, string $timezone): Carbon
    {
        if ($referenceDate instanceof Carbon) {
            return Carbon::create(
                $referenceDate->year,
                $referenceDate->month,
                $referenceDate->day,
                0,
                0,
                0,
                $timezone
            );
        }

        if (is_string($referenceDate) && $referenceDate !== '') {
            return Carbon::parse($referenceDate, $timezone);
        }

        return Carbon::now($timezone);
    }

    /**
     * Récupère ou crée la configuration du rapport mensuel pour une entreprise.
     */
    public function getOrCreateConfig(Company $company): MonthlyReportConfig
    {
        return MonthlyReportConfig::firstOrCreate(
            ['company_id' => $company->id],
            [
                'is_enabled'        => false,
                'send_day'          => 2,
                'send_hour'         => 8,
                'send_minute'       => 0,
                'recipients'        => null,
                'report_format'     => 'both',
                'auto_send_enabled' => true,
            ]
        );
    }

    /**
     * Résout les destinataires autorisés du rapport mensuel pour une entreprise.
     *
     * @param Company $company
     * @return array<int, string>
     */
    public function resolveRecipients(Company $company): array
    {
        $config = MonthlyReportConfig::where('company_id', $company->id)->first();

        // 1. D'abord MonthlyReportConfig.recipients si configuré et non vide
        if ($config && !empty($config->recipients) && is_array($config->recipients)) {
            $validRecipients = [];
            foreach ($config->recipients as $email) {
                $cleaned = strtolower(trim($email));
                if (filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                    $validRecipients[] = $cleaned;
                }
            }
            if (!empty($validRecipients)) {
                return array_values(array_unique($validRecipients));
            }
        }

        // 2. Sinon fallback : e-mail de l'entreprise + utilisateurs RH / Company / Paie
        $recipients = [];

        if (!empty($company->email) && filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = strtolower(trim($company->email));
        }

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

        if (!empty($company->settings['monthly_report_recipients']) && is_array($company->settings['monthly_report_recipients'])) {
            foreach ($company->settings['monthly_report_recipients'] as $configuredEmail) {
                $email = strtolower(trim($configuredEmail));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
        }

        return array_values(array_unique($recipients));
    }

    /**
     * Génère le rapport PDF via DomPDF au format A4 portrait.
     *
     * @param array $reportData
     * @return string Contenu binaire du fichier PDF
     */
    public function generatePdf(array $reportData): string
    {
        $pdf = Pdf::loadView('reports.attendance.monthly_pdf', $reportData);
        $pdf->setPaper('a4', 'portrait');
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
     * @return string Contenu binaire du fichier Excel
     */
    public function generateExcel(array $reportData): string
    {
        return Excel::raw(new MonthlyAttendanceExport($reportData), \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Génère et expédie le rapport mensuel des pointages pour une entreprise.
     *
     * @param Company $company
     * @param Carbon|string|null $referenceMonth
     * @param bool $force
     * @param array|null $customRecipients
     * @param bool $isManual
     * @param bool $isTargetMonth
     * @return array{success: bool, message: string, recipients: array, history_id: int|null}
     */
    public function sendMonthlyReportForCompany(
        Company $company,
        $referenceMonth = null,
        bool $force = false,
        ?array $customRecipients = null,
        bool $isManual = false,
        bool $isTargetMonth = false,
        ?string $formatOverride = null,
        ?string $generationKey = null
    ): array {
        $timezone = $this->resolveTimezone($company);
        $date = $this->resolveReferenceDate($referenceMonth, $timezone);

        $reportMonth = ($isTargetMonth ? $date : $date->copy()->subMonthNoOverflow())->format('Y-m');
        $lock = Cache::lock("monthly-attendance-report:{$company->id}:{$reportMonth}", 900);

        if (!$lock->get()) {
            return [
                'success'    => false,
                'message'    => 'Une génération de ce rapport est déjà en cours.',
                'recipients' => [],
                'history_id' => null,
                'retryable'  => true,
            ];
        }

        try {
            return $this->processMonthlyReportForCompany(
                $company,
                $referenceMonth,
                $force,
                $customRecipients,
                $isManual,
                $isTargetMonth,
                $formatOverride,
                $generationKey
            );
        } finally {
            $lock->release();
        }
    }

    private function processMonthlyReportForCompany(
        Company $company,
        $referenceMonth,
        bool $force,
        ?array $customRecipients,
        bool $isManual,
        bool $isTargetMonth,
        ?string $formatOverride,
        ?string $generationKey
    ): array {
        $timezone = $this->resolveTimezone($company);
        $referenceDate = $this->resolveReferenceDate($referenceMonth, $timezone);
        $targetMonth = $isTargetMonth ? $referenceDate : $referenceDate->copy()->subMonthNoOverflow();
        $reportMonth = $targetMonth->format('Y-m');
        $monthStart = Carbon::createFromFormat('!Y-m', $reportMonth, $timezone);
        $startDateStr = $monthStart->toDateString();
        $endDateStr = $monthStart->copy()->endOfMonth()->toDateString();
        $config = $this->getOrCreateConfig($company);
        $format = $formatOverride ?? $config->report_format;
        if (!in_array($format, ['pdf', 'excel', 'both'], true)) {
            throw new \InvalidArgumentException('Format de rapport mensuel invalide.');
        }
        $generationKey = $isManual
            ? ($generationKey ?: (string) Str::uuid())
            : 'automatic';

        // Vérifier si déjà envoyé ce mois sauf si $force
        $existingHistory = MonthlyReportHistory::where('company_id', $company->id)
            ->where('report_month', $reportMonth)
            ->where('generation_key', $generationKey)
            ->first();

        if ($existingHistory && $existingHistory->send_status === 'sent' && (!$force || $isManual)) {
            Log::info("Rapport mensuel déjà envoyé pour {$company->name} ({$reportMonth}). Envoi ignoré.");
            return [
                'success'    => false,
                'message'    => "Rapport mensuel déjà envoyé pour le mois {$reportMonth}.",
                'recipients' => $existingHistory->recipients_sent ?? [],
                'history_id' => $existingHistory->id,
                'retryable'  => false,
            ];
        }

        $previouslySent = $existingHistory && $existingHistory->send_status === 'failed'
            ? ($existingHistory->recipients_sent ?? [])
            : [];

        // Créer ou réutiliser l'entrée d'historique avec statut pending
        $history = MonthlyReportHistory::updateOrCreate(
            [
                'company_id'   => $company->id,
                'report_month' => $reportMonth,
                'generation_key' => $generationKey,
            ],
            [
                'period_start'      => $startDateStr,
                'period_end'        => $endDateStr,
                'is_manual'         => $isManual,
                'format'            => $format,
                'generation_status' => 'pending',
                'send_status'       => 'pending',
                'error_message'     => null,
                'recipients_sent'   => $previouslySent,
                'file_path_pdf'     => null,
                'file_path_excel'   => null,
            ]
        );

        $pdfContent = null;
        $excelContent = null;
        $slug = Str::slug($company->name ?: 'entreprise');
        $storageDir = "reports/monthly/{$company->id}";
        $fileSuffix = $isManual ? '_' . Str::substr($generationKey, 0, 8) : '';
        $pdfPath = null;
        $excelPath = null;

        try {
            $reportData = $this->buildReportData($company, $referenceMonth, $isTargetMonth);

            // Génération PDF si requis
            if ($format === 'pdf' || $format === 'both') {
                $pdfContent = $this->generatePdf($reportData);
                $pdfPath = "{$storageDir}/rapport_mensuel_{$reportMonth}_{$slug}{$fileSuffix}.pdf";
                if (!Storage::disk('local')->put($pdfPath, $pdfContent)) {
                    throw new \RuntimeException('Impossible d’enregistrer le rapport PDF.');
                }
                $history->file_path_pdf = $pdfPath;
            }

            // Génération Excel si requis
            if ($format === 'excel' || $format === 'both') {
                $excelContent = $this->generateExcel($reportData);
                $excelPath = "{$storageDir}/rapport_mensuel_{$reportMonth}_{$slug}{$fileSuffix}.xlsx";
                if (!Storage::disk('local')->put($excelPath, $excelContent)) {
                    throw new \RuntimeException('Impossible d’enregistrer le rapport Excel.');
                }
                $history->file_path_excel = $excelPath;
            }

            $history->generation_status = 'success';
            $history->generated_at = now();
            $history->save();
        } catch (\Throwable $e) {
            foreach ([$pdfPath, $excelPath] as $partialPath) {
                if (!$partialPath) {
                    continue;
                }

                try {
                    if (Storage::disk('local')->exists($partialPath) && !Storage::disk('local')->delete($partialPath)) {
                        Log::warning("Impossible de supprimer un fichier partiel de rapport : {$partialPath}");
                    }
                } catch (\Throwable $cleanupException) {
                    Log::warning("Erreur lors du nettoyage d’un fichier partiel de rapport : {$partialPath}", [
                        'exception' => $cleanupException,
                    ]);
                }
            }

            Log::error("Échec de génération des documents pour {$company->name} ({$reportMonth}) : " . $e->getMessage(), [
                'exception' => $e,
            ]);
            $history->generation_status = 'failed';
            $history->send_status = 'failed';
            $history->error_message = 'Erreur génération fichiers: ' . $e->getMessage();
            $history->save();

            return [
                'success'    => false,
                'message'    => "Erreur de génération des documents : " . $e->getMessage(),
                'recipients' => [],
                'history_id' => $history->id,
                'retryable'  => true,
            ];
        }

        // Destinataires effectifs
        $recipients = $customRecipients !== null ? $customRecipients : $reportData['recipients'];

        if (empty($recipients)) {
            Log::warning("Aucun destinataire autorisé trouvé pour {$company->name} ({$reportMonth}).");
            $history->send_status = 'skipped';
            $history->error_message = "Aucun destinataire autorisé trouvé.";
            $history->save();

            return [
                'success'    => false,
                'message'    => "Aucun destinataire RH autorisé trouvé.",
                'recipients' => [],
                'history_id' => $history->id,
                'retryable'  => false,
            ];
        }

        // Envoi des emails
        try {
            foreach ($recipients as $recipientEmail) {
                if (in_array(strtolower($recipientEmail), array_map('strtolower', $history->recipients_sent ?? []), true)) {
                    continue;
                }

                $mailable = new MonthlyAttendanceReportMail($reportData, $pdfContent, $excelContent);
                Mail::to($recipientEmail)->send($mailable);
                $history->recipients_sent = array_values(array_unique(array_merge(
                    $history->recipients_sent ?? [],
                    [$recipientEmail]
                )));
                $history->save();
            }

            $history->send_status = 'sent';
            $history->recipients_sent = array_values(array_unique(array_merge(
                $history->recipients_sent ?? [],
                $recipients
            )));
            $history->error_message = null;
            $history->save();

            Log::info("Rapport mensuel envoyé avec succès pour {$company->name} ({$reportMonth}) à : " . implode(', ', $recipients));

            return [
                'success'    => true,
                'message'    => "Rapport mensuel envoyé avec succès à " . count($recipients) . " destinataire(s).",
                'recipients' => $recipients,
                'history_id' => $history->id,
                'retryable'  => false,
            ];
        } catch (\Throwable $e) {
            Log::error("Erreur SMTP lors de l'envoi du rapport mensuel pour {$company->name} ({$reportMonth}) : " . $e->getMessage(), [
                'exception' => $e,
            ]);

            $history->send_status = 'failed';
            $history->error_message = 'Erreur envoi email: ' . $e->getMessage();
            $history->save();

            return [
                'success'    => false,
                'message'    => "Erreur lors de l'expédition de l'e-mail : " . $e->getMessage(),
                'recipients' => $recipients,
                'history_id' => $history->id,
                'retryable'  => true,
            ];
        }
    }
}
