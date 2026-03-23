<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_consultant_entries', function (Blueprint $table) {
            $table->decimal('am_earnings', 12, 4)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_consultant_entries', function (Blueprint $table) {
            $table->dropColumn('am_earnings');
        });
    }
};
