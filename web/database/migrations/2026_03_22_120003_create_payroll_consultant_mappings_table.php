<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_consultant_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('raw_name');
            $table->foreignId('consultant_id')->nullable()->constrained('consultants')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['raw_name', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_consultant_mappings');
    }
};
