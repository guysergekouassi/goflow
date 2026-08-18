<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jours fériés / chômés saisis manuellement par l'entreprise.
     *
     * Sans cette table, tout jour ouvré sans pointage est compté comme une
     * absence injustifiée : les employés étaient pénalisés les jours fériés.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->date('date');
            $table->date('end_date')->nullable()->comment('Pour un pont / plusieurs jours consécutifs');
            // Un férié à date fixe (1er janvier, 1er mai…) se répète chaque année :
            // on ne compare alors que le jour et le mois.
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
