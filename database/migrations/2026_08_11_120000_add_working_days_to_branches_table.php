<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rythme hebdomadaire propre à chaque site.
 *
 * L'entreprise ne portait qu'un « working_days_per_week » (un nombre), qui ne
 * sait exprimer que « fermé le dimanche » ou « fermé samedi et dimanche ».
 * Insuffisant dès qu'un site a ses propres jours : une boutique ouverte du
 * mardi au samedi ferme le dimanche ET le lundi.
 *
 * On stocke donc la liste des jours ouvrés, en numérotation ISO
 * (1 = lundi … 7 = dimanche). NULL = le site suit le rythme de l'entreprise,
 * ce qui préserve le comportement de tous les sites existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('working_days')
                ->nullable()
                ->after('is_active')
                ->comment('Jours ouvres ISO 1-7. NULL = herite du rythme entreprise');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('working_days');
        });
    }
};
