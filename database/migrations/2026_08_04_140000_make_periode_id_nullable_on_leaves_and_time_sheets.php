<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une demande d'absence ne doit pas dépendre de la configuration de la paie.
     *
     * Jusqu'ici, `periode_id` était NOT NULL : un employé dont l'entreprise
     * n'avait aucune période de paie recevait « Aucune période de paie trouvée »
     * et ne pouvait pas soumettre sa demande depuis le mobile. La période est
     * désormais rattachée quand elle existe (et à l'approbation si elle est
     * créée entre-temps), sans jamais bloquer la demande.
     */
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable()->change();
        });

        Schema::table('time_sheets', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Les lignes sans période doivent être rattachées avant de revenir
        // en arrière, sinon la contrainte NOT NULL échouerait.
        Schema::table('leaves', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable(false)->change();
        });

        Schema::table('time_sheets', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable(false)->change();
        });
    }
};
