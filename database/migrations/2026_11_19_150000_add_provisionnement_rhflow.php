<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ouverture des comptes GPFlow depuis RHFlow :
 * - lien entre une société GPFlow et l'entreprise RHFlow d'origine
 *   (rhflow_source = URL de l'instance RHFlow, car prod et dev ont leurs propres ID) ;
 * - lien entre un salarié GPFlow et sa fiche RHFlow ;
 * - lots d'accès : identifiants et mots de passe provisoires des comptes créés,
 *   chiffrés, téléchargeables pendant quelques jours puis supprimés.
 *
 * Idempotente : chaque élément n'est ajouté que s'il manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('companies', 'rhflow_source')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('rhflow_source', 191)->nullable()->after('created_by');
                $table->unsignedBigInteger('rhflow_company_id')->nullable()->after('rhflow_source');
                $table->index(['rhflow_source', 'rhflow_company_id'], 'companies_rhflow_index');
            });
        }

        if (!Schema::hasColumn('employees', 'rhflow_employee_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('rhflow_employee_id')->nullable()->after('company_id');
                $table->index(['company_id', 'rhflow_employee_id'], 'employees_rhflow_index');
            });
        }

        if (!Schema::hasTable('lots_acces')) {
            Schema::create('lots_acces', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('libelle');
                $table->longText('contenu');
                $table->unsignedInteger('nb_comptes')->default(0);
                // DATETIME et non TIMESTAMP : sous MySQL/MariaDB sans explicit_defaults_for_timestamp,
                // un TIMESTAMP obligatoire se remet à l'heure courante à chaque mise à jour de la ligne
                // (le lot expirerait dès qu'on y ajoute des comptes ou qu'on le télécharge).
                $table->dateTime('expire_le')->index();
                $table->dateTime('telecharge_le')->nullable();
                $table->unsignedInteger('telechargements')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lots_acces');

        if (Schema::hasColumn('employees', 'rhflow_employee_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('employees_rhflow_index');
                $table->dropColumn('rhflow_employee_id');
            });
        }

        if (Schema::hasColumn('companies', 'rhflow_source')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropIndex('companies_rhflow_index');
                $table->dropColumn(['rhflow_source', 'rhflow_company_id']);
            });
        }
    }
};
