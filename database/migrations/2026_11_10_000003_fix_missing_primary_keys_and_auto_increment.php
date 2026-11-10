<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige les tables où la colonne `id` n'a ni PRIMARY KEY ni AUTO_INCREMENT.
 *
 * Dans certaines tables (ex: work_locations, sectors, settings, time_sheets, etc.),
 * la clé primaire et l'AUTO_INCREMENT ont sauté ou n'ont pas été posés.
 */
return new class extends Migration
{
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

            // Récupère les infos de la colonne `id`
            $cols = DB::select(
                "SELECT COLUMN_NAME, EXTRA, COLUMN_TYPE, DATA_TYPE, COLUMN_KEY FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'id'",
                [$dbName, $tableName]
            );

            if (empty($cols)) {
                continue;
            }

            $col = $cols[0];

            // Vérifie que c'est bien un type entier
            $dataType = strtolower($col->DATA_TYPE);
            if (!in_array($dataType, $this->integerTypes)) {
                continue;
            }

            // Si déjà AUTO_INCREMENT, rien à faire
            if (stripos($col->EXTRA, 'auto_increment') !== false) {
                continue;
            }

            $colType = $col->COLUMN_TYPE; // ex: "bigint unsigned" ou "int"

            // Si la table n'a pas de PRIMARY KEY, on ajoute la PRIMARY KEY avec AUTO_INCREMENT
            if ($col->COLUMN_KEY !== 'PRI') {
                // Vérifie si la table a une autre PRIMARY KEY
                $hasPri = DB::select(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_KEY = 'PRI'",
                    [$dbName, $tableName]
                );

                if (empty($hasPri)) {
                    // Aucune PK existante : on ajoute PRIMARY KEY et AUTO_INCREMENT directement
                    DB::statement("ALTER TABLE `{$tableName}` MODIFY `id` {$colType} NOT NULL AUTO_INCREMENT PRIMARY KEY");
                } else {
                    // Déjà une autre PK : on modifie juste id en AUTO_INCREMENT KEY
                    DB::statement("ALTER TABLE `{$tableName}` MODIFY `id` {$colType} NOT NULL AUTO_INCREMENT");
                }
            } else {
                // Déjà PRI mais manque AUTO_INCREMENT
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `id` {$colType} NOT NULL AUTO_INCREMENT");
            }

            // Réajuste l'AUTO_INCREMENT au dessus du max
            $maxId = DB::table($tableName)->max('id') ?? 0;
            if ($maxId > 0) {
                DB::statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = " . ($maxId + 1));
            }

            echo "Fixed PK & AUTO_INCREMENT on table: {$tableName}\n";
        }
    }

    public function down(): void
    {
        // Pas de rollback pour conserver l'intégrité
    }
};
