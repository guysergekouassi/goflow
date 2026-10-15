<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contrôle d'effectif : une absence n'est accordée que si le département
     * garde au moins `min_staff_present` employés présents chaque jour.
     * Le RH peut passer outre ; son motif et son identité sont conservés.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'min_staff_present')) {
                $table->unsignedSmallInteger('min_staff_present')->default(1)
                    ->comment('Effectif minimum présent ; 0 = pas de contrôle')->after('is_active');
            }
        });

        foreach (['leaves', 'demandes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'effectif_derogation')) {
                    $table->text('effectif_derogation')->nullable()
                        ->comment('Motif de la dérogation à l\'effectif minimum du département')->after('status');
                }
                if (!Schema::hasColumn($tableName, 'effectif_derogation_by')) {
                    $table->unsignedBigInteger('effectif_derogation_by')->nullable()->after('effectif_derogation');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'min_staff_present')) {
                $table->dropColumn('min_staff_present');
            }
        });

        foreach (['leaves', 'demandes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['effectif_derogation', 'effectif_derogation_by'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
