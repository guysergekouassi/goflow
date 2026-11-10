<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige l'absence d'AUTO_INCREMENT sur branches.id.
 *
 * La table a été créée avec `bigint unsigned NOT NULL` sans AUTO_INCREMENT,
 * ce qui provoque SQLSTATE[HY000] 1364 lors de tout INSERT sans id explicite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Récupère la valeur max actuelle pour ne pas casser les enregistrements existants
        $maxId = DB::table('branches')->max('id') ?? 0;

        DB::statement('ALTER TABLE `branches` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // Repositionne le compteur auto_increment juste au-dessus du max actuel
        if ($maxId > 0) {
            DB::statement("ALTER TABLE `branches` AUTO_INCREMENT = " . ($maxId + 1));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `branches` MODIFY `id` BIGINT UNSIGNED NOT NULL');
    }
};

