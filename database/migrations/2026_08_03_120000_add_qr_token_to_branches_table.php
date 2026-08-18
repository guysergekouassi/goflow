<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jeton du QR Code unique par succursale.
     * Encodé dans le QR et vérifié à chaque scan : le supprimer révoque
     * immédiatement tous les QR déjà imprimés pour ce site.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('qr_token', 40)->nullable()->after('is_active');
            $table->timestamp('qr_generated_at')->nullable()->after('qr_token');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'qr_generated_at']);
        });
    }
};
