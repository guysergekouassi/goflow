<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Employees\Models\Employee;
use Modules\Time\Models\PointageHorsSite;
use Modules\Time\Models\Pointeuse;

/**
 * Pointages GPS hors des sites connus.
 *
 * Être loin de tout site n'est pas une anomalie en soi : l'employé peut être
 * en visite chez un client, sur un autre site ou dans une localité dont
 * l'emplacement a changé. Le pointage est donc enregistré avec le motif de
 * l'employé et confié à son supérieur hiérarchique, qui le valide ou le refuse.
 *
 * Le supérieur est cherché dans l'ordre :
 *   1. le gestionnaire du service de l'employé (departments.manager_id)
 *   2. le gestionnaire de sa succursale (branches.manager_id)
 *   3. le compte entreprise
 * L'employé ne peut jamais être son propre validateur.
 *
 * Un pointage validé est recopié dans `pointeuses` à son heure d'origine,
 * avec un name_device commençant par « Pointage GPS » pour que les rapports
 * le reconnaissent. Tant qu'il est en attente, il n'y figure pas.
 */
class PointageHorsSiteService
{
    /** Un second envoi identique dans ce délai est un double appui, pas un nouveau pointage. */
    private const DOUBLON_MINUTES = 5;

    /**
     * @return array{user: ?User, level: string}
     */
    public function resolveValidator(Employee $employee): array
    {
        $candidats = [];

        if ($employee->department_id) {
            $candidats['service'] = Department::find($employee->department_id)?->manager_id;
        }
        if ($employee->branch_id) {
            $candidats['succursale'] = Branch::find($employee->branch_id)?->manager_id;
        }

        foreach ($candidats as $level => $userId) {
            if (!$userId || (int) $userId === (int) $employee->user_id) {
                continue;
            }

            $user = User::find($userId);
            if ($user && (int) ($user->is_active ?? 1) !== 0) {
                return ['user' => $user, 'level' => $level];
            }
        }

        $ownerId = Company::find($employee->company_id)?->user_id;

        return ['user' => $ownerId ? User::find($ownerId) : null, 'level' => 'entreprise'];
    }

    /**
     * Enregistre le pointage en attente et prévient le supérieur.
     *
     * @param array{type: string, latitude: float, longitude: float, accuracy: ?int,
     *              nearest_location_id: ?int, nearest_distance: ?int, motif: string, device_name: ?string} $data
     */
    public function submit(Employee $employee, array $data): PointageHorsSite
    {
        $now = Carbon::now();

        $doublon = PointageHorsSite::where('employee_id', $employee->id)
            ->where('type', $data['type'])
            ->enAttente()
            ->where('pointed_at', '>=', $now->copy()->subMinutes(self::DOUBLON_MINUTES))
            ->latest('pointed_at')
            ->first();

        if ($doublon) {
            return $doublon;
        }

        // Retard / départ anticipé selon l'horaire habituel de l'employé
        // (sa succursale et son service), puisqu'aucun site n'a été reconnu.
        $schedules = app(BranchScheduleResolver::class);
        $location = $employee->branch_id
            ? $schedules->resolveForEmployee((int) $employee->company_id, (int) $employee->branch_id, $employee->department_id)
            : null;
        $evaluation = $schedules->evaluateScan($location, $data['type'], $now);

        $validator = $this->resolveValidator($employee);

        $pointage = PointageHorsSite::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'pointed_at' => $now,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'gps_accuracy' => $data['accuracy'] ?? null,
            'nearest_location_id' => $data['nearest_location_id'] ?? null,
            'nearest_distance' => $data['nearest_distance'] ?? null,
            'motif' => $data['motif'],
            'device_name' => $data['device_name'] ?? null,
            'scan_status' => $evaluation['status'],
            'duree_retard' => $evaluation['minutes'],
            'status' => PointageHorsSite::EN_ATTENTE,
            'validator_user_id' => $validator['user']?->id,
            'validator_level' => $validator['level'],
        ]);

        if ($validator['user']) {
            $this->notifier(
                $validator['user']->id,
                $employee->user_id,
                $pointage,
                'Pointage hors site à valider',
                $employee->name . ' a pointé ' . ($data['type'] === 'sortie' ? 'sa sortie' : 'son entrée')
                    . ' le ' . $now->format('d/m/Y à H:i') . ' hors des sites connus. Motif : « ' . $data['motif'] . ' ».',
                'warning'
            );
        }

        return $pointage;
    }

    public function canDecide(User $user, PointageHorsSite $pointage): bool
    {
        if ((int) $pointage->company_id !== (int) $user->company_id) {
            return false;
        }

        return $user->type === 'company' || (int) $pointage->validator_user_id === (int) $user->id;
    }

    /**
     * Valide ou refuse un pointage en attente.
     *
     * @throws \RuntimeException si le pointage a déjà été traité
     */
    public function decide(PointageHorsSite $pointage, User $by, bool $valider, ?string $commentaire = null): PointageHorsSite
    {
        $pointage = DB::transaction(function () use ($pointage, $by, $valider, $commentaire) {
            $pointage = PointageHorsSite::whereKey($pointage->id)->lockForUpdate()->firstOrFail();

            if ($pointage->status !== PointageHorsSite::EN_ATTENTE) {
                throw new \RuntimeException('Ce pointage a déjà été ' . mb_strtolower($pointage->libelleStatut()) . '.');
            }

            if ($valider) {
                $pointage->pointeuse_id = $this->creerPointeuse($pointage)->id;
            }

            $pointage->status = $valider ? PointageHorsSite::VALIDE : PointageHorsSite::REFUSE;
            $pointage->decided_by = $by->id;
            $pointage->decided_at = Carbon::now();
            $pointage->decision_comment = $commentaire ?: null;
            $pointage->save();

            return $pointage;
        });

        $employee = $pointage->employee;
        if ($employee && $employee->user_id) {
            $quand = $pointage->pointed_at->format('d/m/Y à H:i');
            $message = $valider
                ? 'Votre pointage ' . mb_strtolower($pointage->libelleType()) . ' hors site du ' . $quand . ' a été validé par ' . $by->name . '.'
                : 'Votre pointage ' . mb_strtolower($pointage->libelleType()) . ' hors site du ' . $quand . ' a été refusé par ' . $by->name . '.';

            if ($commentaire) {
                $message .= ' Commentaire : « ' . $commentaire . ' ».';
            }

            $this->notifier(
                $employee->user_id,
                $by->id,
                $pointage,
                $valider ? 'Pointage hors site validé' : 'Pointage hors site refusé',
                $message,
                $valider ? 'success' : 'danger'
            );
        }

        return $pointage;
    }

    private function creerPointeuse(PointageHorsSite $pointage): Pointeuse
    {
        $employee = $pointage->employee;
        $quand = $pointage->pointed_at;

        return Pointeuse::create([
            'employee_id' => $pointage->employee_id,
            'auth_date_time' => $quand,
            'auth_date' => $quand->toDateString(),
            'auth_time' => $quand->toTimeString(),
            'type' => $pointage->type,
            'direction' => Company::find($pointage->company_id)?->name ?? 'N/A',
            'name_device' => 'Pointage GPS hors site ' . ($pointage->device_name ?? ''),
            'no_device' => $employee?->user_id,
            'emp_id' => $pointage->employee_id,
            'card_no' => 'N/A',
            'name_emp' => $employee?->name,
            'location_id' => null,
            'latitude' => $pointage->latitude,
            'longitude' => $pointage->longitude,
            'gps_accuracy' => $pointage->gps_accuracy,
            'distance_site' => $pointage->nearest_distance,
            'status' => 1,
            'company_id' => $pointage->company_id,
            'scan_status' => $pointage->scan_status,
            'duree_retard' => $pointage->duree_retard,
        ]);
    }

    private function notifier(int $userId, ?int $senderId, PointageHorsSite $pointage, string $title, string $message, string $color): void
    {
        try {
            Notification::create([
                'type' => 'pointage_hors_site',
                'title' => $title,
                'message' => $message,
                'icon' => 'fas fa-map-marker-alt',
                'color' => $color,
                'is_read' => false,
                'user_id' => $userId,
                'sender_id' => $senderId,
                'source_type' => PointageHorsSite::class,
                'source_id' => $pointage->id,
                'action_url' => route('company.times.pointages-hors-site.index'),
            ]);
        } catch (\Throwable $e) {
            // Une notification manquée ne doit jamais faire perdre le pointage ni la décision
            Log::warning('Notification pointage hors site non envoyée : ' . $e->getMessage(), ['pointage_id' => $pointage->id]);
        }
    }
}
