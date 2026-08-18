<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numéro Mobile Money saisi par le client au moment de la commande.
 *
 * Le formulaire de paiement le demande (et le valide) depuis toujours, mais la
 * colonne n'existait pas : l'attribut était écarté silencieusement à la création
 * de la commande. Conséquence, `$order->phone` valait toujours null dans
 * GeniusPayService::createPayment() et le super admin n'avait aucun numéro
 * payeur à comparer lors d'un rapprochement manuel.
 *
 * Ce numéro reste une piste d'audit : l'association commande <-> compte se fait
 * par `order_number` (renvoyé signé par la passerelle), jamais par le numéro,
 * puisqu'un tiers peut payer pour l'entreprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
