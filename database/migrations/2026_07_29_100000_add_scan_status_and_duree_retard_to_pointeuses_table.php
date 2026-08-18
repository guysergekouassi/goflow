<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pointeuses', function (Blueprint $table) {
            if (!Schema::hasColumn('pointeuses', 'scan_status')) {
                $table->string('scan_status')->nullable()->default('a_l_heure')->after('status');
            }
            if (!Schema::hasColumn('pointeuses', 'duree_retard')) {
                $table->integer('duree_retard')->default(0)->comment('Durée du retard en minutes')->after('scan_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pointeuses', function (Blueprint $table) {
            if (Schema::hasColumn('pointeuses', 'scan_status')) {
                $table->dropColumn('scan_status');
            }
            if (Schema::hasColumn('pointeuses', 'duree_retard')) {
                $table->dropColumn('duree_retard');
            }
        });
    }
};
