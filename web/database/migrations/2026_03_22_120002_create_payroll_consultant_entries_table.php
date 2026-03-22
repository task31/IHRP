<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_consultant_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('consultant_name');
            $table->unsignedSmallInteger('year');
            $table->decimal('revenue', 12, 4)->default(0);
            $table->decimal('cost', 12, 4)->default(0);
            $table->decimal('margin', 12, 4)->default(0);
            $table->decimal('pct_of_total', 12, 4)->default(0);
            $table->foreignId('consultant_id')->nullable()->constrained('consultants')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'consultant_name', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_consultant_entries');
    }
};
