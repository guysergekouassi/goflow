<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasTable('jobs')) {
                DB::statement('ALTER TABLE `jobs` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }

            if (Schema::hasTable('failed_jobs')) {
                DB::statement('ALTER TABLE `failed_jobs` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down operation needed as AUTO_INCREMENT is the expected standard definition for Laravel queues.
    }
};

