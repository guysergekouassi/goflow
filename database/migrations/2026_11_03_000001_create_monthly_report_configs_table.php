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
        Schema::create('monthly_report_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->tinyInteger('send_day')->default(2)->comment('Jour du mois (1-31)');
            $table->tinyInteger('send_hour')->default(8)->comment('Heure (0-23)');
            $table->tinyInteger('send_minute')->default(0)->comment('Minute (0-59)');
            $table->json('recipients')->nullable()->comment('Adresses e-mail destinataires');
            $table->string('report_format', 10)->default('both')->comment('pdf, excel, both');
            $table->boolean('auto_send_enabled')->default(true);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_report_configs');
    }
};
