<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('monthly_report_histories', 'generation_key')) {
            Schema::table('monthly_report_histories', function (Blueprint $table) {
                $table->string('generation_key', 50)->default('automatic')->after('report_month');
            });

            foreach (DB::table('monthly_report_histories')
                ->where('is_manual', true)
                ->orderBy('id')
                ->pluck('id') as $id) {
                DB::table('monthly_report_histories')
                    ->where('id', $id)
                    ->update(['generation_key' => 'manual-legacy-' . $id]);
            }
        }

        if (Schema::hasIndex('monthly_report_histories', 'm_report_hist_comp_month_manual_unique')) {
            Schema::table('monthly_report_histories', function (Blueprint $table) {
                $table->dropUnique('m_report_hist_comp_month_manual_unique');
            });
        }

        if (!Schema::hasIndex('monthly_report_histories', 'm_report_hist_comp_month_generation_unique')) {
            Schema::table('monthly_report_histories', function (Blueprint $table) {
                $table->unique(
                    ['company_id', 'report_month', 'generation_key'],
                    'm_report_hist_comp_month_generation_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('monthly_report_histories', 'm_report_hist_comp_month_generation_unique')) {
            Schema::table('monthly_report_histories', function (Blueprint $table) {
                $table->dropUnique('m_report_hist_comp_month_generation_unique');
            });
        }

        if (Schema::hasColumn('monthly_report_histories', 'generation_key')) {
            Schema::table('monthly_report_histories', function (Blueprint $table) {
                $table->dropColumn('generation_key');
            });
        }

        if (!Schema::hasIndex('monthly_report_histories', 'm_report_hist_comp_month_manual_unique')) {
            Schema::table('monthly_report_histories', function (Blueprint $table) {
                $table->unique(
                    ['company_id', 'report_month', 'is_manual'],
                    'm_report_hist_comp_month_manual_unique'
                );
            });
        }
    }
};
