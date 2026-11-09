<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('holidays', 'is_worked')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->boolean('is_worked')->default(false)->after('is_recurring');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('holidays', 'is_worked')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropColumn('is_worked');
            });
        }
    }
};
