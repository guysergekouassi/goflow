<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie et corrige l'AUTO_INCREMENT manquant sur toutes les tables ayant une colonne `id` primaire.
 * Exclut les tables déjà corrigées (jobs, failed_jobs, branches).
 */
return new class extends Migration
{
    /** Tables déjà traitées par des migrations précédentes. */
    private array $alreadyFixed = ['jobs', 'failed_jobs', 'branches'];

    /** Types de colonnes numériques pouvant recevoir AUTO_INCREMENT. */
    private array $integerTypes = ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();

        foreach ($tables as $tableRow) {
            $tableName = array_values((array) $tableRow)[0];

            if (in_array($tableName, $this->alreadyFixed)) {
                continue;
            }

            // Récupère les infos de la colonne `id` PRIMARY
            $cols = DB::select(
                "SELECT COLUMN_NAME, EXTRA, COLUMN_TYPE, DATA_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'id' AND COLUMN_KEY = 'PRI'",
                [$dbName, $tableName]
            );

            if (empty($cols)) {
                continue;
            }

            $col = $cols[0];

            // Ignore si déjà AUTO_INCREMENT
            if (stripos($col->EXTRA, 'auto_increment') !== false) {
                continue;
            }

            // Ignore si ce n'est pas un type entier (ex: varchar, uuid, char…)
            $dataType = strtolower($col->DATA_TYPE);
            if (!in_array($dataType, $this->integerTypes)) {
                continue;
            }

            $colType = $col->COLUMN_TYPE; // ex: "bigint unsigned"

            // Reposition du compteur
            $maxId = DB::table($tableName)->max('id') ?? 0;

            DB::statement("ALTER TABLE `{$tableName}` MODIFY `id` {$colType} NOT NULL AUTO_INCREMENT");

            if ($maxId > 0) {
                DB::statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = " . ($maxId + 1));
            }

            echo "Fixed AUTO_INCREMENT on table: {$tableName}\n";
        }
    }

    public function down(): void
    {
        // Pas de rollback : on ne peut pas deviner l'état initial de chaque table.
    }
};
