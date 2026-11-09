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
        if (!Schema::hasTable('monthly_report_histories')) {
            Schema::create('monthly_report_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('report_month', 7)->comment('Format YYYY-MM'); // ex: 2026-10
                $table->date('period_start');
                $table->date('period_end');
                $table->timestamp('generated_at')->nullable();
                $table->string('format', 10)->default('both');
                $table->string('generation_status', 20)->default('pending'); // pending, success, failed
                $table->string('send_status', 20)->default('pending'); // pending, sent, failed, skipped
                $table->json('recipients_sent')->nullable();
                $table->string('file_path_pdf')->nullable();
                $table->string('file_path_excel')->nullable();
                $table->text('error_message')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamps();
                $table->unique(['company_id', 'report_month', 'is_manual'], 'm_report_hist_comp_month_manual_unique');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index(['company_id', 'report_month']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_report_histories');
    }
};
