<?php

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;
use Modules\Leaves\Models\Leave;
use Modules\Leaves\Models\LeaveType;
use Modules\Time\Models\TimeSheet;

/**
 * Demandes d'absence de l'application mobile.
 *
 * Elles sont enregistrées dans `leaves`, sous le type « Absence » de
 * l'entreprise (et non sous un type de congé comme « Congé annuel »).
 * Une fois approuvées, elles sont recopiées dans `time_sheets`, seule table
 * lue par les rapports de présence, la paie et le résumé du mobile.
 */
class AbsenceService
{
    public const TITRE_TYPE = 'Absence';

    /** Heures retenues par jour d'absence (comme le formulaire d'absence du web). */
    public const HEURES_PAR_JOUR = 8;

    /**
     * Type de congé « Absence » de l'entreprise, créé à la première demande.
     */
    public function typeFor(int $companyId): LeaveType
    {
        $type = LeaveType::where('company_id', $companyId)
            ->where('title', 'like', '%absence%')
            ->orderBy('id')
            ->first();

        return $type ?? LeaveType::create([
            'title' => self::TITRE_TYPE,
            'days' => 0,
            'company_id' => $companyId,
            'is_active' => true,
            'created_by' => Company::find($companyId)?->user_id,
        ]);
    }

    /**
     * Inscrit une demande approuvée dans `time_sheets`, pour qu'elle apparaisse
     * « justifiée » dans les rapports de présence et compte dans le résumé.
     * À n'appeler qu'au passage au statut approuvé.
     */
    public function recordApproved(Leave $leave): TimeSheet
    {
        $startDate = Carbon::parse($leave->start_date);
        $endDate = Carbon::parse($leave->end_date);

        // Jours couverts, bornes incluses
        $days = $startDate->diffInDays($endDate) + 1;

        return TimeSheet::create([
            'employee_id' => $leave->employee_id,
            'periode_id' => $leave->periode_id,
            'date' => $leave->start_date,
            'arrival_date' => $leave->end_date,
            'hours' => round($days * self::HEURES_PAR_JOUR),
            'motif_justify' => 'Oui',
            // Motif affiché dans la gestion de présence : le type demandé
            'type_permis' => $leave->leaveType->title ?? 'Absence justifiée',
            'remark' => $leave->leave_reason,
            'retenue' => $days,
            'deduc_abs' => 0,
            'statut' => 'approved',
            'document' => null,
            'monthpaie' => $startDate->format('Y-m'),
            'company_id' => $leave->company_id,
        ]);
    }
}
