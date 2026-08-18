<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue les jours fériés chômés de ceux qui sont travaillés.
 *
 * Toutes les entreprises ne ferment pas les jours fériés. Sans cette
 * distinction, un férié neutralisait systématiquement l'absence : impossible
 * de constater qu'un employé attendu ce jour-là ne s'est pas présenté.
 *
 * false (défaut) = chômé, l'entreprise ferme, aucune absence n'est comptée.
 * true            = travaillé, la journée compte comme un jour ouvré normal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->boolean('is_worked')
                ->default(false)
                ->after('is_recurring')
                ->comment('true = ferie travaille (journee ouvree normale)');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('is_worked');
        });
    }
};
