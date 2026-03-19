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
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->tinyInteger('week_number')->nullable();

            $table->string('description');

            $table->decimal('hours', 6, 2)->nullable();
            $table->decimal('rate', 12, 4);

            $table->decimal('multiplier', 4, 2)->default(1.00);
            $table->decimal('amount', 12, 4);

            $table->integer('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
