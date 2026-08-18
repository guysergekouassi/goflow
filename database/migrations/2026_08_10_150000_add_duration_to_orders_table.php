<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durée facturée, en mois, figée sur la commande.
 *
 * Le renouvellement accepte 1 à 12 mois et le montant en tient compte, mais la
 * durée n'était nulle part enregistrée : à la confirmation du paiement, rien ne
 * permettait de savoir jusqu'à quand prolonger l'abonnement.
 *
 * (Une migration `add_duration_to_orders_table` datée du 05/05 existe déjà mais
 * son corps est vide et elle a été exécutée ; d'où cette nouvelle migration.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration')->default(1)->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
