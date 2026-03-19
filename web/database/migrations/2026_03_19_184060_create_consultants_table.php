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
        Schema::create('consultants', function (Blueprint $table) {
            $table->id();

            $table->string('full_name');
            $table->decimal('pay_rate', 12, 4);
            $table->decimal('bill_rate', 12, 4);

            $table->string('state');
            $table->string('industry_type')->default('other');

            $table->foreignId('client_id')->constrained('clients');

            $table->date('project_start_date')->nullable();
            $table->date('project_end_date')->nullable();

            $table->boolean('w9_on_file')->default(false);
            $table->string('w9_file_path')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultants');
    }
};
