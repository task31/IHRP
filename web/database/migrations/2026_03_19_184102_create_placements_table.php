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
        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('job_title', 255)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('pay_rate', 12, 4);
            $table->decimal('bill_rate', 12, 4);
            $table->enum('status', ['active', 'ended', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placements');
    }
};
