<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pointages GPS faits hors du rayon de tout site connu (visite client,
     * autre site, localité qui a changé). Ils ne sont pas refusés : ils
     * attendent ici la décision du supérieur hiérarchique et ne sont recopiés
     * dans `pointeuses` qu'une fois validés. Tant qu'ils sont en attente, aucun
     * calcul de présence ni de paie ne les voit.
     */
    public function up(): void
    {
        if (Schema::hasTable('pointages_hors_site')) {
            return;
        }

        Schema::create('pointages_hors_site', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('type', 10)->comment('entree ou sortie');
            $table->dateTime('pointed_at')->comment('Heure réelle du pointage, reprise telle quelle à la validation');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('gps_accuracy')->nullable()->comment('Précision GPS en mètres');
            $table->unsignedBigInteger('nearest_location_id')->nullable()->comment('Site connu le plus proche');
            $table->integer('nearest_distance')->nullable()->comment('Distance au site connu le plus proche, en mètres');
            $table->text('motif')->comment('Motif saisi par l\'employé');
            $table->string('device_name')->nullable();
            $table->string('scan_status', 20)->nullable();
            $table->integer('duree_retard')->nullable();
            $table->string('status', 20)->default('en_attente')->comment('en_attente, valide ou refuse');
            $table->unsignedBigInteger('validator_user_id')->nullable()->comment('Supérieur chargé de la décision');
            $table->string('validator_level', 20)->nullable()->comment('service, succursale ou entreprise');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->unsignedBigInteger('pointeuse_id')->nullable()->comment('Pointage créé à la validation');
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['validator_user_id', 'status']);
            $table->index(['employee_id', 'pointed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pointages_hors_site');
    }
};
