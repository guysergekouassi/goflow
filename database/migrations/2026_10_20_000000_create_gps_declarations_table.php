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
        if (!Schema::hasTable('gps_declarations')) {
            Schema::create('gps_declarations', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('employee_name')->nullable();
                $table->string('employee_department')->nullable();
                $table->unsignedBigInteger('company_id')->index();

                $table->string('site_id')->nullable();
                $table->string('site_name')->nullable();

                $table->enum('type', ['entree', 'sortie'])->default('entree');
                $table->date('date');
                $table->time('time');
                $table->time('original_time')->nullable();

                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->float('accuracy')->nullable();
                $table->integer('distance_meters')->default(0);
                $table->boolean('is_mocked')->default(false);

                $table->enum('status', ['En attente', 'Validée', 'Rejetée', 'Modifiée'])->default('En attente')->index();
                $table->boolean('is_late')->default(false);
                $table->integer('late_minutes')->default(0);

                // Champs hors site
                $table->boolean('is_offsite')->default(false)->index();
                $table->string('offsite_location_label')->nullable();
                $table->text('offsite_reason')->nullable();
                $table->string('mission_reference')->nullable();

                // Audit & validation
                $table->text('rejection_reason')->nullable();
                $table->text('correction_justification')->nullable();
                $table->string('validated_by')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->json('history')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'date']);
                $table->index(['employee_id', 'date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_declarations');
    }
};
