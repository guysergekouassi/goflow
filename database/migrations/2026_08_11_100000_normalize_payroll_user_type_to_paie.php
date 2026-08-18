<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Uniformise le type des utilisateurs « Paie » sur la valeur « paie ».
 *
 * Le formulaire de création enregistrait « payroll », alors que tout le reste
 * de l'application attend « paie » : User::TYPES, la redirection après
 * connexion (LoginController), le garde-fou du dashboard RH (HRController),
 * le scope staff() et le menu latéral. Un compte « payroll » n'entrait donc
 * dans aucune de ces branches : redirigé vers le dashboard employé, sans
 * aucune entrée de menu, et refusé sur /hr/dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('type', 'payroll')
            ->update(['type' => 'paie']);
    }

    /**
     * Rétablit la valeur d'origine. Sans effet de bord : « payroll » n'était
     * produit que par ce formulaire, aucun autre code ne l'écrit.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('type', 'paie')
            ->update(['type' => 'payroll']);
    }
};
