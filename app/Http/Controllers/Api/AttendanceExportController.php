<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Company;
use App\Models\PaiePeriode;
use Modules\Employees\Models\Employee;
use Modules\Time\Models\Pointeuse;
use Modules\Time\Models\TimeSheet;
use Modules\Time\Models\Overtime;

class AttendanceExportController extends Controller
{
    /**
     * Liste des périodes de paie disponibles
     * GET /api/presences/periodes
     */
    public function getPeriodes(Request $request)
    {
        $companyId = $request->query('company_id') ?? $request->header('X-Company-Id');

        $periodes = PaiePeriode::when($companyId, function ($q) use ($companyId) {
                return $q->where('company_id', $companyId);
            })
            ->select('id', 'nom', 'code', 'date_debut', 'date_fin', 'statut', 'company_id')
            ->orderByDesc('date_debut')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $periodes,
        ]);
    }

    /**
     * Exportation consolidée des présences pour le Sas de Contrôle RH de RHFlow
     * GET /api/presences/export
     */
    public function export(Request $request)
    {
        $companyId = $request->query('company_id') ?? $request->header('X-Company-Id');
        $periodeId = $request->query('periode_id');
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $monthpaie = $request->query('monthpaie');

        $periode = null;
        if (!empty($periodeId)) {
            $periode = PaiePeriode::find($periodeId);
            if ($periode) {
                $companyId = $companyId ?: $periode->company_id;
                $startDate = $startDate ?: ($periode->date_debut ? Carbon::parse($periode->date_debut)->format('Y-m-d') : null);
                $endDate   = $endDate ?: ($periode->date_fin ? Carbon::parse($periode->date_fin)->format('Y-m-d') : null);
                $monthpaie = $monthpaie ?: $periode->nom;
            }
        }

        $company = $companyId ? Company::find($companyId) : null;

        // 1. Pointeuses (Pointages bruts entrée/sortie)
        $pointeusesQuery = Pointeuse::query();
        if ($companyId) {
            $pointeusesQuery->where('company_id', $companyId);
        }
        if ($periodeId && $startDate && $endDate) {
            $pointeusesQuery->where(function ($q) use ($periodeId, $startDate, $endDate) {
                $q->where('periode_id', $periodeId)
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->whereNull('periode_id')
                          ->whereBetween('auth_date', [$startDate, $endDate]);
                  });
            });
        } elseif ($periodeId) {
            $pointeusesQuery->where('periode_id', $periodeId);
        } elseif ($startDate && $endDate) {
            $pointeusesQuery->whereBetween('auth_date', [$startDate, $endDate]);
        }
        $pointeuses = $pointeusesQuery->orderBy('auth_date_time', 'asc')->get();

        // 2. TimeSheets (Absences, congés, feuilles de temps)
        $timeSheetsQuery = TimeSheet::query();
        if ($companyId) {
            $timeSheetsQuery->where('company_id', $companyId);
        }
        if ($periodeId && $startDate && $endDate) {
            $timeSheetsQuery->where(function ($q) use ($periodeId, $startDate, $endDate, $monthpaie) {
                $q->where('periode_id', $periodeId)
                  ->orWhereBetween('date', [$startDate, $endDate]);
                if ($monthpaie) {
                    $q->orWhere('monthpaie', $monthpaie);
                }
            });
        } elseif ($periodeId) {
            $timeSheetsQuery->where('periode_id', $periodeId);
        } elseif ($startDate && $endDate) {
            $timeSheetsQuery->whereBetween('date', [$startDate, $endDate]);
        } elseif ($monthpaie) {
            $timeSheetsQuery->where('monthpaie', $monthpaie);
        }
        $timeSheets = $timeSheetsQuery->orderBy('date', 'asc')->get();

        // 3. Overtimes (Heures supplémentaires)
        $overtimesQuery = Overtime::query();
        if ($companyId) {
            $overtimesQuery->where('company_id', $companyId);
        }
        if ($periodeId && $startDate && $endDate) {
            $overtimesQuery->where(function ($q) use ($periodeId, $startDate, $endDate) {
                $q->where('periode_id', $periodeId)
                  ->orWhereBetween('start_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            });
        } elseif ($periodeId) {
            $overtimesQuery->where('periode_id', $periodeId);
        } elseif ($startDate && $endDate) {
            $overtimesQuery->whereBetween('start_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }
        $overtimes = $overtimesQuery->orderBy('start_date', 'asc')->get();

        // 4. Salariés concernés
        $employeeIds = collect()
            ->merge($pointeuses->pluck('employee_id'))
            ->merge($timeSheets->pluck('employee_id'))
            ->merge($overtimes->pluck('employee_id'))
            ->filter()
            ->unique()
            ->values();

        $employees = Employee::whereIn('id', $employeeIds)
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->get()
            ->keyBy('id');

        // 5. Synthèse par salarié (pour affichage immédiat dans le Sas RHFlow)
        $employeesSummary = [];
        $totalRetardsMinutes = 0;
        $totalAbsencesRetenues = 0;
        $totalHeuresSup = 0;
        $totalAnomalies = 0;

        foreach ($employeeIds as $empId) {
            $emp = $employees->get($empId);
            $empPointeuses = $pointeuses->where('employee_id', $empId);
            $empTimeSheets = $timeSheets->where('employee_id', $empId);
            $empOvertimes  = $overtimes->where('employee_id', $empId);

            $joursPointes = $empPointeuses->pluck('auth_date')->unique()->count();
            $dureeRetard = $empPointeuses->sum('duree_retard');
            $retardsCount = $empPointeuses->where('duree_retard', '>', 0)->count();

            // Absences : retenues non justifiées (qui réduisent nbre_jour en paie)
            $absencesNonJustifiees = (int) $empTimeSheets->where('motif_justify', 'Non')->sum('retenue');
            $absencesJustifiees = (int) $empTimeSheets->where('motif_justify', 'Oui')->sum('retenue');

            // Heures supplémentaires
            $quarHeure       = (float) $empOvertimes->sum('quar_heure');
            $heureAudd       = (float) $empOvertimes->sum('heure_audd');
            $heureNuitFerie  = (float) $empOvertimes->sum('heure_nuit_ferie');
            $heureDimFerie   = (float) $empOvertimes->sum('heure_dim_ferie');
            $heureNuitDim    = (float) $empOvertimes->sum('heure_nuit_dim_ferie');
            $totalHsHours    = $quarHeure + $heureAudd + $heureNuitFerie + $heureDimFerie + $heureNuitDim;
            $montantHs       = (float) $empOvertimes->sum('montant');

            // Anomalies à arbitrer par le RH
            $anomalies = [];
            if ($retardsCount > 0) {
                $anomalies[] = "{$retardsCount} retard(s) totalisant {$dureeRetard} min";
            }
            if ($absencesNonJustifiees > 0) {
                $anomalies[] = "{$absencesNonJustifiees} jour(s) d'absence non justifiée à déduire";
            }
            if ($totalHsHours > 0) {
                $anomalies[] = "{$totalHsHours} h supplémentaires déclarées";
            }

            $statutSuggere = count($anomalies) > 0 ? 'a_arbitrer' : 'conforme';
            if ($statutSuggere === 'a_arbitrer') {
                $totalAnomalies++;
            }

            $totalRetardsMinutes += $dureeRetard;
            $totalAbsencesRetenues += $absencesNonJustifiees;
            $totalHeuresSup += $totalHsHours;

            // Calcul prévisionnel des jours travaillés pour la paie
            // Base conventionnelle 30 jours (ou jours ouvrés) moins absences retenues
            $baseJours = 30;
            $joursPaieProposes = max(0, $baseJours - $absencesNonJustifiees);

            $employeesSummary[] = [
                'employee_id'                 => $empId,
                'matricule'                   => $emp ? ($emp->matricule ?? $emp->id) : (string)$empId,
                'nom'                         => $emp ? ($emp->name ?? trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''))) : "Employé #{$empId}",
                'email'                       => $emp ? ($emp->email ?? optional($emp->user)->email) : null,
                'total_pointages'             => $empPointeuses->count(),
                'jours_pointes'               => $joursPointes,
                'retards_count'               => $retardsCount,
                'retards_minutes'             => $dureeRetard,
                'absences_non_justifiees_j'   => $absencesNonJustifiees,
                'absences_justifiees_j'       => $absencesJustifiees,
                'jours_base'                  => $baseJours,
                'jours_paie_proposes'         => $joursPaieProposes,
                'heures_sup' => [
                    'quar_heure'              => $quarHeure,
                    'heure_audd'              => $heureAudd,
                    'heure_nuit_ferie'        => $heureNuitFerie,
                    'heure_dim_ferie'         => $heureDimFerie,
                    'heure_nuit_dim_ferie'    => $heureNuitDim,
                    'total_heures'            => $totalHsHours,
                    'montant_estime'          => $montantHs,
                ],
                'anomalies'                   => $anomalies,
                'statut_suggere'              => $statutSuggere,
            ];
        }

        return response()->json([
            'status'       => 'success',
            'source'       => 'RHFlow-GP',
            'exported_at'  => Carbon::now()->toIso8601String(),
            'filters'      => [
                'company_id' => $companyId ? (int)$companyId : null,
                'periode_id' => $periodeId ? (int)$periodeId : null,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'monthpaie'  => $monthpaie,
            ],
            'periode'      => $periode ? [
                'id'         => $periode->id,
                'nom'        => $periode->nom,
                'code'       => $periode->code,
                'date_debut' => $periode->date_debut ? Carbon::parse($periode->date_debut)->format('Y-m-d') : null,
                'date_fin'   => $periode->date_fin ? Carbon::parse($periode->date_fin)->format('Y-m-d') : null,
            ] : null,
            'company'      => $company ? [
                'id'   => $company->id,
                'name' => $company->name,
            ] : null,
            'statistiques' => [
                'total_salaries_concernes' => count($employeeIds),
                'total_pointages'          => $pointeuses->count(),
                'total_retards_minutes'    => $totalRetardsMinutes,
                'total_absences_retenues'  => $totalAbsencesRetenues,
                'total_heures_sup'         => $totalHeuresSup,
                'total_a_arbitrer'         => $totalAnomalies,
            ],
            'employees_summary' => $employeesSummary,
            'raw_data' => [
                'pointeuses'  => $pointeuses,
                'time_sheets' => $timeSheets,
                'overtimes'   => $overtimes,
            ],
        ]);
    }
}

