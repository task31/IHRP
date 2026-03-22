<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('check_date');
            $table->decimal('gross_pay', 12, 4)->default(0);
            $table->decimal('net_pay', 12, 4)->default(0);
            $table->decimal('federal_tax', 12, 4)->default(0);
            $table->decimal('state_tax', 12, 4)->default(0);
            $table->decimal('social_security', 12, 4)->default(0);
            $table->decimal('medicare', 12, 4)->default(0);
            $table->decimal('retirement_401k', 12, 4)->default(0);
            $table->decimal('health_insurance', 12, 4)->default(0);
            $table->decimal('other_deductions', 12, 4)->default(0);
            $table->decimal('commission_subtotal', 12, 4)->default(0);
            $table->decimal('salary_subtotal', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'check_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};
