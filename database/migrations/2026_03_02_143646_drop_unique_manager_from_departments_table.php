<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('department_manager_id_company_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->unique(['manager_id', 'company_id'], 'department_manager_id_company_id_index');
        });
    }
};
