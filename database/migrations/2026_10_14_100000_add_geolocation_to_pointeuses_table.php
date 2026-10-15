<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Position GPS des pointages à distance (site client) : conservée pour
     * permettre aux RH de vérifier où l'employé se trouvait au moment du pointage.
     */
    public function up(): void
    {
        Schema::table('pointeuses', function (Blueprint $table) {
            if (!Schema::hasColumn('pointeuses', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('location_id');
            }
            if (!Schema::hasColumn('pointeuses', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('pointeuses', 'gps_accuracy')) {
                $table->integer('gps_accuracy')->nullable()->comment('Précision GPS en mètres')->after('longitude');
            }
            if (!Schema::hasColumn('pointeuses', 'distance_site')) {
                $table->integer('distance_site')->nullable()->comment('Distance au site en mètres')->after('gps_accuracy');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pointeuses', function (Blueprint $table) {
            foreach (['latitude', 'longitude', 'gps_accuracy', 'distance_site'] as $column) {
                if (Schema::hasColumn('pointeuses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
