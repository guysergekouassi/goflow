<?php

namespace App\Services;

use App\Models\Department;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Schema;
use Modules\Employees\Models\Demande;
use Modules\Employees\Models\Employee;
use Modules\Leaves\Models\Leave;

/**
 * Contrôle d'effectif avant d'accorder une absence.
 *
 * Un département ne doit jamais se vider parce que trop d'absences ont été
 * accordées en même temps. Pour chaque jour de la période demandée, on compte
 * les employés actifs du département déjà absents (congés accordés ou en
 * cours, demandes d'absence accordées), on y ajoute le demandeur, et l'on
 * vérifie qu'il reste au moins `departments.min_staff_present` présents.
 *
 * Le RH peut passer outre en donnant un motif (colonnes effectif_derogation*).
 * Les trois points de validation l'utilisent : LeavesController::changeStatus,
 * DemandeController::validateDemande et ApiAppController::updateRequestStatus.
 */
class DepartmentStaffingGuard
{
    /** Minimum appliqué tant que le département n'en précise pas. */
    public const DEFAULT_MIN_STAFF_PRESENT = 1;

    /** Statuts d'un congé qui retirent l'employé de l'effectif présent. */
    private const LEAVE_ABSENT_STATUSES = ['Approuvé', 'Démarré'];

    /** Statuts d'une demande d'absence accordée. */
    private const DEMANDE_ABSENT_STATUSES = ['Approved', 'Approuvé'];

    /** Garde-fou sur la longueur de la période examinée. */
    private const MAX_DAYS = 366;

    public function checkLeave(Leave $leave): array
    {
        return $this->check($leave->employee_id, $leave->start_date, $leave->end_date, 'leaves', $leave->id);
    }

    public function checkDemande(Demande $demande): array
    {
        // Seules les demandes d'absence datées retirent quelqu'un de l'effectif
        if ($demande->categorie_demandes !== 'absence' || !$demande->start_date) {
            return $this->result(true);
        }

        return $this->check($demande->employee_id, $demande->start_date, $demande->end_date, 'demandes', $demande->id);
    }

    /**
     * @param  string|null $ignoreTable 'leaves' ou 'demandes' : la demande en cours
     *                                  de validation, à ne pas compter deux fois
     * @return array{ok: bool, departement: ?string, minimum: int, effectif: int, periodes: array, message: ?string}
     */
    public function check($employeeId, $start, $end, ?string $ignoreTable = null, ?int $ignoreId = null): array
    {
        $employee = Employee::find($employeeId);
        $department = $employee && $employee->department_id ? Department::find($employee->department_id) : null;

        if (!$department || !$start) {
            return $this->result(true);
        }

        $minimum = Schema::hasColumn('departments', 'min_staff_present')
            ? (int) ($department->min_staff_present ?? self::DEFAULT_MIN_STAFF_PRESENT)
            : self::DEFAULT_MIN_STAFF_PRESENT;

        if ($minimum <= 0) {
            return $this->result(true, $department->name, 0);
        }

        $collegues = Employee::where('company_id', $employee->company_id)
            ->where('department_id', $department->id)
            ->where('is_active', 1)
            ->pluck('name', 'id');

        $effectif = $collegues->count();
        $debut = Carbon::parse($start)->startOfDay();
        $fin = $end ? Carbon::parse($end)->startOfDay() : $debut->copy();

        if ($fin->lt($debut)) {
            [$debut, $fin] = [$fin, $debut];
        }
        if ($debut->diffInDays($fin) >= self::MAX_DAYS) {
            $fin = $debut->copy()->addDays(self::MAX_DAYS - 1);
        }

        $absences = $this->absencesAccordees($collegues->keys()->all(), $debut, $fin, $ignoreTable, $ignoreId);

        // Jours où l'effectif tombe sous le minimum, regroupés en périodes continues
        $periodes = [];
        $courante = null;

        foreach (CarbonPeriod::create($debut, $fin) as $jour) {
            $absents = $absences
                ->filter(fn ($a) => $a['debut']->lte($jour) && $a['fin']->gte($jour))
                ->pluck('employee_id')
                ->push((int) $employee->id)
                ->unique()
                ->filter(fn ($id) => $collegues->has($id));

            $presents = $effectif - $absents->count();

            if ($presents >= $minimum) {
                $courante = null;
                continue;
            }

            $dejaAbsents = $absents->reject(fn ($id) => $id === (int) $employee->id)
                ->map(fn ($id) => $collegues[$id])
                ->values()
                ->all();

            if ($courante !== null && $periodes[$courante]['fin'] === $jour->copy()->subDay()->toDateString()) {
                $periodes[$courante]['fin'] = $jour->toDateString();
                $periodes[$courante]['presents'] = min($periodes[$courante]['presents'], $presents);
                $periodes[$courante]['absents'] = array_values(array_unique(array_merge($periodes[$courante]['absents'], $dejaAbsents)));
            } else {
                $periodes[] = [
                    'debut' => $jour->toDateString(),
                    'fin' => $jour->toDateString(),
                    'presents' => $presents,
                    'absents' => $dejaAbsents,
                ];
                $courante = array_key_last($periodes);
            }
        }

        $result = $this->result(empty($periodes), $department->name, $minimum, $effectif, $periodes);
        $result['message'] = $result['ok'] ? null : $this->message($result, $employee->name);

        return $result;
    }

    /**
     * Trace la dérogation du RH sur la demande (sans l'enregistrer). Sans effet
     * tant que la migration des colonnes effectif_derogation n'est pas passée.
     */
    public function recordDerogation($demande, ?string $motif, ?int $userId): void
    {
        if (!Schema::hasColumn($demande->getTable(), 'effectif_derogation')) {
            return;
        }

        $demande->effectif_derogation = $motif;
        $demande->effectif_derogation_by = $userId;
    }

    /**
     * Absences accordées des employés donnés qui chevauchent la période,
     * sous la forme [employee_id, debut, fin].
     */
    private function absencesAccordees(array $employeeIds, Carbon $debut, Carbon $fin, ?string $ignoreTable, ?int $ignoreId)
    {
        if (empty($employeeIds)) {
            return collect();
        }

        $conges = Leave::whereIn('employee_id', $employeeIds)
            ->whereIn('status', self::LEAVE_ABSENT_STATUSES)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $fin)
            ->whereDate('end_date', '>=', $debut)
            ->when($ignoreTable === 'leaves' && $ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['employee_id', 'start_date', 'end_date']);

        $demandes = Demande::whereIn('employee_id', $employeeIds)
            ->where('categorie_demandes', 'absence')
            ->whereIn('status', self::DEMANDE_ABSENT_STATUSES)
            ->whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $fin)
            ->where(function ($q) use ($debut) {
                $q->whereDate('end_date', '>=', $debut)
                    ->orWhere(fn ($q) => $q->whereNull('end_date')->whereDate('start_date', '>=', $debut));
            })
            ->when($ignoreTable === 'demandes' && $ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['employee_id', 'start_date', 'end_date']);

        return $conges->concat($demandes)->map(fn ($a) => [
            'employee_id' => (int) $a->employee_id,
            'debut' => Carbon::parse($a->start_date)->startOfDay(),
            'fin' => Carbon::parse($a->end_date ?: $a->start_date)->startOfDay(),
        ]);
    }

    private function result(bool $ok, ?string $departement = null, int $minimum = 0, int $effectif = 0, array $periodes = []): array
    {
        return [
            'ok' => $ok,
            'departement' => $departement,
            'minimum' => $minimum,
            'effectif' => $effectif,
            'periodes' => $periodes,
            'message' => null,
        ];
    }

    private function message(array $result, ?string $demandeur): string
    {
        $lignes = array_map(function ($p) {
            $quand = $p['debut'] === $p['fin']
                ? 'le ' . Carbon::parse($p['debut'])->format('d/m/Y')
                : 'du ' . Carbon::parse($p['debut'])->format('d/m/Y') . ' au ' . Carbon::parse($p['fin'])->format('d/m/Y');

            return $quand . ' : ' . $p['presents'] . ' présent(s)'
                . ($p['absents'] ? ' (déjà absents : ' . implode(', ', $p['absents']) . ')' : '');
        }, $result['periodes']);

        return '⚠️ Effectif insuffisant dans le département « ' . $result['departement'] . ' » '
            . '(minimum ' . $result['minimum'] . ' présent(s) sur ' . $result['effectif'] . ') '
            . 'si l\'absence de ' . ($demandeur ?: 'cet employé') . ' est accordée : '
            . implode(' ; ', $lignes) . '.';
    }
}
