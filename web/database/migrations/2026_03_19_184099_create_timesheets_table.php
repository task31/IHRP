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
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultant_id')->constrained('consultants');
            $table->foreignId('client_id')->constrained('clients');

            $table->date('pay_period_start');
            $table->date('pay_period_end');

            // Immutable snapshots (never updated after insert)
            $table->decimal('pay_rate_snapshot', 12, 4);
            $table->decimal('bill_rate_snapshot', 12, 4);
            $table->string('state_snapshot');
            $table->string('industry_type_snapshot')->default('other');

            // OT rule applied when this timesheet was processed
            $table->string('ot_rule_applied')->default('FLSA Weekly Only');

            // Week 1 breakdown
            $table->decimal('week1_regular_hours', 12, 4)->default(0);
            $table->decimal('week1_ot_hours', 12, 4)->default(0);
            $table->decimal('week1_dt_hours', 12, 4)->default(0);

            $table->decimal('week1_regular_pay', 12, 4)->default(0);
            $table->decimal('week1_ot_pay', 12, 4)->default(0);
            $table->decimal('week1_dt_pay', 12, 4)->default(0);

            $table->decimal('week1_regular_billable', 12, 4)->default(0);
            $table->decimal('week1_ot_billable', 12, 4)->default(0);
            $table->decimal('week1_dt_billable', 12, 4)->default(0);

            // Week 2 breakdown
            $table->decimal('week2_regular_hours', 12, 4)->default(0);
            $table->decimal('week2_ot_hours', 12, 4)->default(0);
            $table->decimal('week2_dt_hours', 12, 4)->default(0);

            $table->decimal('week2_regular_pay', 12, 4)->default(0);
            $table->decimal('week2_ot_pay', 12, 4)->default(0);
            $table->decimal('week2_dt_pay', 12, 4)->default(0);

            $table->decimal('week2_regular_billable', 12, 4)->default(0);
            $table->decimal('week2_ot_billable', 12, 4)->default(0);
            $table->decimal('week2_dt_billable', 12, 4)->default(0);

            // Period totals
            $table->decimal('total_regular_hours', 12, 4)->default(0);
            $table->decimal('total_ot_hours', 12, 4)->default(0);
            $table->decimal('total_dt_hours', 12, 4)->default(0);

            $table->decimal('total_consultant_cost', 12, 4)->default(0);
            $table->decimal('total_client_billable', 12, 4)->default(0);

            // Margin fields
            $table->decimal('gross_revenue', 12, 4)->default(0);
            $table->decimal('gross_margin_dollars', 12, 4)->default(0);
            $table->decimal('gross_margin_percent', 8, 4)->default(0);

            // Invoice linkage
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('invoice_status')->default('pending');

            $table->unique(['consultant_id', 'pay_period_start', 'pay_period_end']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
