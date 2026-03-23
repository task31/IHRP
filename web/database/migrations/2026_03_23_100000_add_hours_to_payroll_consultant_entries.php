<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_consultant_entries', function (Blueprint $table) {
            $table->decimal('hours', 12, 4)->default(0)->after('year');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_consultant_entries', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
    }
};
