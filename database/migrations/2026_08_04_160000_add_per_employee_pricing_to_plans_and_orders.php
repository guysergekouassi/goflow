<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarification mixte : forfait mensuel + montant par employé.
     *
     * Exemple : 5 000 FCFA de forfait + 1 000 FCFA par employé
     *   3 employés => 5 000 + 3 000 = 8 000 FCFA
     *   5 employés => 5 000 + 5 000 = 10 000 FCFA
     *
     * `included_employees` permet d'offrir N employés dans le forfait avant
     * de facturer les suivants (0 = tous les employés sont facturés).
     *
     * Les colonnes ajoutées sur `orders` figent l'effectif retenu au moment
     * de la facturation : une facture ne doit jamais changer de montant parce
     * que l'entreprise a recruté depuis.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_per_employee', 10, 2)->default(0)->after('price_yearly');
            $table->unsignedInteger('included_employees')->default(0)->after('price_per_employee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('employees_count')->nullable()->after('plan_id');
            $table->decimal('base_amount', 12, 2)->nullable()->after('employees_count');
            $table->decimal('employees_amount', 12, 2)->nullable()->after('base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_per_employee', 'included_employees']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['employees_count', 'base_amount', 'employees_amount']);
        });
    }
};
