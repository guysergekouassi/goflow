<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use Carbon\Carbon;
use Modules\Employees\Models\Employee;
use Modules\Settings\Models\WorkLocation;

/**
 * Logique horaire portée par le QR Code unique d'une succursale.
 *
 * Un site n'a qu'un seul QR Code : ce sont les horaires qui varient d'un
 * département à l'autre (ex. Commercial 08:00-08:30 / 17:00-17:30,
 * Informatique 10:30-11:00 / 20:00-20:30). Au scan, on ne lit donc jamais
 * l'horaire dans le QR : on le déduit du département de l'employé.
 *
 * Chaîne de repli, dans l'ordre :
 *   1. WorkLocation du couple (site + département de l'employé)
 *   2. WorkLocation par défaut du site (department_id = NULL)
 *   3. Aucun horaire configuré → pointage enregistré en « mode libre »
 *
 * Cette classe est l'unique source de vérité : l'API mobile
 * (ApiAppController) et le portail web (BranchPortalController) l'utilisent
 * toutes les deux, afin qu'un même scan produise toujours le même statut.
 */
class BranchScheduleResolver
{
    /** Horaires appliqués par défaut quand un emplacement ne les précise pas. */
    public const DEFAULT_CHECK_IN_END    = '08:30:00';
    public const DEFAULT_CHECK_OUT_START = '17:00:00';

    /**
     * Emplacement — et donc tranche horaire — applicable à un employé qui
     * scanne le QR Code de son site.
     *
     * @return WorkLocation|null null = aucun horaire configuré (mode libre)
     */
    public function resolveForEmployee(int $companyId, int $branchId, $departmentId = null): ?WorkLocation
    {
        // 1. Horaire spécifique au département de l'employé
        if ($departmentId) {
            $location = WorkLocation::with(['branch', 'department'])
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('department_id', $departmentId)
                ->where('is_active', 1)
                ->first();

            if ($location) {
                return $location;
            }
        }

        // 2. Horaire par défaut du site (aucun département précisé)
        return $this->siteDefaultLocation($companyId, $branchId);
    }

    /**
     * Horaire par défaut du site : emplacement rattaché à la succursale sans
     * département. C'est lui qui s'applique quand le site ne définit pas
     * d'horaire spécifique par département.
     */
    public function siteDefaultLocation(int $companyId, int $branchId): ?WorkLocation
    {
        return WorkLocation::with(['branch', 'department'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereNull('department_id')
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Statut d'un scan (à l'heure / retard / en avance) selon l'emplacement
     * résolu pour l'employé.
     *
     * @param  WorkLocation|null $location null → mode libre, aucun contrôle
     * @param  string            $type     'entree' ou 'sortie'
     * @return array{status:string, minutes:int, limit:string|null}
     */
    public function evaluateScan(?WorkLocation $location, string $type, Carbon $now): array
    {
        if (!$location) {
            return ['status' => 'a_l_heure', 'minutes' => 0, 'limit' => null];
        }

        $scanTime = Carbon::parse($now->format('H:i:s'));

        if ($type === 'entree') {
            // Retard : arrivée après l'heure limite de montée du département
            $limit = $this->normalizeTime($location->check_in_end ?? self::DEFAULT_CHECK_IN_END);

            if ($scanTime->gt(Carbon::parse($limit))) {
                return [
                    'status'  => 'retard',
                    'minutes' => $this->minutesBetween($limit, $scanTime),
                    'limit'   => $limit,
                ];
            }

            return ['status' => 'a_l_heure', 'minutes' => 0, 'limit' => $limit];
        }

        // Sortie — départ anticipé : avant l'heure de début de descente
        $limit = $this->normalizeTime(
            $location->check_out_start ?? $location->check_out_end ?? self::DEFAULT_CHECK_OUT_START
        );

        if ($scanTime->lt(Carbon::parse($limit))) {
            return [
                'status'  => 'en_avance',
                'minutes' => $this->minutesBetween($limit, $scanTime),
                'limit'   => $limit,
            ];
        }

        return ['status' => 'a_l_heure', 'minutes' => 0, 'limit' => $limit];
    }

    /**
     * Récapitulatif des tranches horaires couvertes par le QR Code du site :
     * un département par ligne, avec l'horaire réellement appliqué et son
     * origine. Sert à afficher, sous le QR, tout ce qu'il englobe.
     *
     * @return array{
     *   site_default: WorkLocation|null,
     *   rows: array<int, array<string, mixed>>,
     *   has_department_schedules: bool
     * }
     */
    public function siteSchedule(Branch $branch): array
    {
        $companyId = (int) $branch->company_id;
        $branchId  = (int) $branch->id;

        $siteDefault = $this->siteDefaultLocation($companyId, $branchId);

        // Emplacements du site déclinés par département, indexés pour la jointure
        $departmentLocations = WorkLocation::with('department')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereNotNull('department_id')
            ->where('is_active', 1)
            ->get()
            ->keyBy('department_id');

        // Départements concernés : ceux rattachés au site, ceux de ses employés,
        // et ceux pour lesquels un horaire a déjà été paramétré sur ce site.
        $employeeDepartmentIds = Employee::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereNotNull('department_id')
            ->distinct()
            ->pluck('department_id');

        $departmentIds = $employeeDepartmentIds
            ->merge($departmentLocations->keys())
            ->merge(
                Department::where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->pluck('id')
            )
            ->unique()
            ->values();

        $departments = Department::whereIn('id', $departmentIds)
            ->orderBy('name')
            ->get();

        $headcounts = Employee::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereNotNull('department_id')
            ->selectRaw('department_id, COUNT(*) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $rows = [];

        foreach ($departments as $department) {
            $location = $departmentLocations->get($department->id);
            $source   = 'department';

            if (!$location) {
                $location = $siteDefault;
                $source   = $siteDefault ? 'site' : 'none';
            }

            $rows[] = [
                'department' => $department,
                'location'   => $location,
                'source'     => $source,
                'headcount'  => (int) ($headcounts[$department->id] ?? 0),
                'check_in'   => $this->formatRange($location, 'check_in_start', 'check_in_end'),
                'check_out'  => $this->formatRange($location, 'check_out_start', 'check_out_end'),
            ];
        }

        // Employés du site sans département : ils suivent l'horaire du site
        $withoutDepartment = Employee::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereNull('department_id')
            ->count();

        if ($withoutDepartment > 0) {
            $rows[] = [
                'department' => null,
                'location'   => $siteDefault,
                'source'     => $siteDefault ? 'site' : 'none',
                'headcount'  => $withoutDepartment,
                'check_in'   => $this->formatRange($siteDefault, 'check_in_start', 'check_in_end'),
                'check_out'  => $this->formatRange($siteDefault, 'check_out_start', 'check_out_end'),
            ];
        }

        return [
            'site_default'             => $siteDefault,
            'rows'                     => $rows,
            'has_department_schedules' => $departmentLocations->isNotEmpty(),
        ];
    }

    /**
     * Plage « début → fin » lisible, ou null si l'emplacement ne la définit pas.
     */
    private function formatRange(?WorkLocation $location, string $startField, string $endField): ?string
    {
        if (!$location) {
            return null;
        }

        $start = $location->{$startField};
        $end   = $location->{$endField};

        if (!$start && !$end) {
            return null;
        }

        $format = function ($value) {
            if (!$value) {
                return '--:--';
            }

            return substr($this->normalizeTime($value), 0, 5);
        };

        return $format($start) . ' → ' . $format($end);
    }

    /**
     * Accepte aussi bien un Carbon (cast du modèle) qu'une chaîne « H:i » ou
     * « H:i:s » et renvoie toujours « H:i:s ».
     */
    private function normalizeTime($raw): string
    {
        if (is_object($raw) && method_exists($raw, 'format')) {
            return $raw->format('H:i:s');
        }

        $value = (string) $raw;

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function minutesBetween(string $limit, Carbon $scanTime): int
    {
        return (int) round(abs(Carbon::parse($limit)->diffInMinutes($scanTime)));
    }
}
