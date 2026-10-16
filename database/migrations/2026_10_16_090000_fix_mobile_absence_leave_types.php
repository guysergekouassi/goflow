<?php

use App\Services\AbsenceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les demandes d'absence de l'app mobile étaient enregistrées sous le
     * premier type de congé trouvé (« Congé annuel », souvent d'une autre
     * entreprise) : elles s'affichaient comme des congés.
     *
     * Une demande créée par l'employé lui-même vient forcément du mobile (le
     * web n'enregistre que des congés saisis par le compte entreprise), qui
     * n'envoie que des absences : elle passe sous le type « Absence » de son
     * entreprise.
     */
    public function up(): void
    {
        $parEntreprise = DB::table('leaves')
            ->join('employees', 'employees.id', '=', 'leaves.employee_id')
            ->whereColumn('leaves.created_by', 'employees.user_id')
            ->whereNotNull('leaves.company_id')
            ->select('leaves.id', 'leaves.company_id')
            ->get()
            ->groupBy('company_id');

        $service = app(AbsenceService::class);

        foreach ($parEntreprise as $companyId => $leaves) {
            DB::table('leaves')
                ->whereIn('id', $leaves->pluck('id'))
                ->update(['leave_type_id' => $service->typeFor((int) $companyId)->id]);
        }
    }

    /**
     * Les types d'origine étaient erronés : rien à restaurer.
     */
    public function down(): void
    {
    }
};
