<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultants', function (Blueprint $table) {
            $table->index('active');
            $table->index('project_end_date');
        });
        Schema::table('placements', function (Blueprint $table) {
            $table->index('status');
            $table->index('start_date');
        });
        Schema::table('timesheets', function (Blueprint $table) {
            $table->index('invoice_status');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('status');
            $table->index('invoice_date');
        });
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->index('check_date');
        });
        Schema::table('payroll_consultant_entries', function (Blueprint $table) {
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::table('consultants', function (Blueprint $table) {
            $table->dropIndex(['active']);
            $table->dropIndex(['project_end_date']);
        });
        Schema::table('placements', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['start_date']);
        });
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropIndex(['invoice_status']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['invoice_date']);
        });
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropIndex(['check_date']);
        });
        Schema::table('payroll_consultant_entries', function (Blueprint $table) {
            $table->dropIndex(['year']);
        });
    }
};
