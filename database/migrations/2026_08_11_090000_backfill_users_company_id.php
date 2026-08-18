<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renseigne « users.company_id » pour les comptes existants.
 *
 * La colonne n'était alimentée que par effet de bord, à l'enregistrement des
 * paramètres de l'entreprise. Tous les comptes créés depuis Paramètres >
 * Utilisateurs (RH, paie, managers de site) sont donc restés à NULL, ce qui
 * les prive de tous les écrans filtrant sur ce champ — dont le pointage
 * collectif, qui ne leur affichait aucune succursale.
 *
 * Trois sources, de la plus fiable à la plus indirecte :
 *   1. l'utilisateur possède l'entreprise (companies.user_id)
 *   2. sa fiche employé porte le company_id
 *   3. son créateur (users.created_by) possède une entreprise
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'company_id')) {
            return;
        }

        // 1. Propriétaires de compte entreprise
        DB::table('companies')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($companies) {
                foreach ($companies as $company) {
                    DB::table('users')
                        ->where('id', $company->user_id)
                        ->whereNull('company_id')
                        ->update(['company_id' => $company->id]);
                }
            });

        // 2. Utilisateurs disposant d'une fiche employé
        if (Schema::hasTable('employees')) {
            DB::table('employees')
                ->whereNotNull('user_id')
                ->whereNotNull('company_id')
                ->orderBy('id')
                ->chunkById(200, function ($employees) {
                    foreach ($employees as $employee) {
                        DB::table('users')
                            ->where('id', $employee->user_id)
                            ->whereNull('company_id')
                            ->update(['company_id' => $employee->company_id]);
                    }
                });
        }

        // 3. Repli : l'entreprise du compte créateur
        $orphans = DB::table('users')
            ->whereNull('company_id')
            ->whereNotNull('created_by')
            ->get(['id', 'created_by']);

        foreach ($orphans as $orphan) {
            $companyId = DB::table('companies')
                ->where('user_id', $orphan->created_by)
                ->value('id');

            if ($companyId) {
                DB::table('users')
                    ->where('id', $orphan->id)
                    ->update(['company_id' => $companyId]);
            }
        }
    }

    /**
     * Irréversible : impossible de distinguer les valeurs rétablies ici de
     * celles déjà présentes. Vider la colonne recasserait les comptes sains.
     */
    public function down(): void
    {
        //
    }
};
